<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Actions\Employees;

use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Colaboradores\Application\Data\Employees\CreateEmployeeData;
use App\Domains\Colaboradores\Application\Data\Employees\NewEmployeeLink;
use App\Domains\Colaboradores\Domain\DTOs\Employees\CreateEmployeeDTO;
use App\Domains\Colaboradores\Domain\Models\Employee;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Organizacao\Application\Queries\Companies\LockActiveCompaniesByCodeQuery;
use App\Domains\Pessoas\Application\Actions\People\CreatePersonAction;
use App\Domains\Pessoas\Application\Actions\SyncCommercialTagsAction;
use App\Domains\Pessoas\Domain\Exceptions\PersonChangeNotAllowed;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Support\Exceptions\ResourceNotAvailable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Cadastra a pessoa, se for nova, e cria um vínculo de colaborador em cada empresa escolhida, numa única transação.
 *
 * Cada empresa é autorizada de forma independente e precisa estar ativa. Um conflito em qualquer empresa desfaz
 * tudo: nenhum vínculo parcial e nenhuma pessoa nova ficam para trás. Cadastrar colaborador não cria usuário.
 */
final readonly class CreateEmployeeAction
{
    public function __construct(
        private CreatePersonAction $createPerson,
        private SyncCommercialTagsAction $syncTags,
        private LockActiveCompaniesByCodeQuery $lockActiveCompanies,
    ) {}

    /**
     * @return list<Employee>
     *
     * @throws AuthorizationException
     * @throws PersonChangeNotAllowed
     * @throws ResourceNotAvailable
     * @throws ValidationException
     */
    public function execute(CreateEmployeeData $data, Client $account): array
    {
        return AuditLog::transaction(function () use ($data, $account): array {
            Client::whereKey($account->id)->lockForUpdate()->firstOrFail();

            $codes = array_map(fn (NewEmployeeLink $link) => $link->company->code, $data->links);
            $companies = $this->lockActiveCompanies->execute($account->id, $codes)->keyBy('code');

            foreach ($companies as $company) {
                // O slug fica literal na chamada para que permissions:sync o descubra.
                Gate::authorize('account.employees.create', AccessNode::company($account->id, $company->id));
            }

            if (is_int($data->person)) {
                $person = Person::where('account_id', $account->id)->lockForUpdate()->findOrFail($data->person);
            } else {
                $person = $this->createPerson->execute($data->person, $account);
            }

            $taken = Employee::where('account_id', $account->id)
                ->where('person_id', $person->id)
                ->whereIn('company_id', $companies->pluck('id'))
                ->pluck('company_id')
                ->all();

            if ($taken !== []) {
                $names = $companies
                    ->filter(fn ($company) => in_array($company->id, $taken, true))
                    ->map(fn ($company) => $company->trade_name ?: $company->name)
                    ->implode(', ');

                throw ValidationException::withMessages([
                    'companies' => 'Esta pessoa já é colaboradora em: '.$names.'. Reative o vínculo na ficha, se estiver inativo.',
                ]);
            }

            Tag::employeeTag($account->id);

            $employees = [];

            foreach ($data->links as $link) {
                $employeeDTO = new CreateEmployeeDTO(
                    workEmail: $link->workEmail,
                    workPhone: $link->workPhone,
                    workWhatsapp: $link->workWhatsapp,
                );

                $employee = Employee::register($employeeDTO, $account->id, $link->company->id, $person->id);

                try {
                    $employee->saveOrFail();
                } catch (UniqueConstraintViolationException) {
                    throw ValidationException::withMessages([
                        'companies' => 'Esta pessoa já é colaboradora em '.$link->company->name.'.',
                    ]);
                }

                $this->syncTags->execute($data->tags, 'employees', $employee);

                $employees[] = $employee;
            }

            return $employees;
        });
    }
}
