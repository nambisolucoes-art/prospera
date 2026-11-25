<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Actions\Employees;

use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Colaboradores\Application\Queries\Employees\ShowEmployeePersonQuery;
use App\Domains\Pessoas\Application\Actions\People\UpdatePersonAction;
use App\Domains\Pessoas\Application\Data\CommercialRelations\CommercialPersonChangesData;
use App\Domains\Pessoas\Domain\Exceptions\PersonChangeNotAllowed;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Support\Exceptions\OperationNotAllowed;
use Illuminate\Validation\ValidationException;

/**
 * Altera os dados centrais da pessoa pela ficha do colaborador.
 *
 * A regra de Pessoas (`UpdatePersonAction`) continua sendo a única autoridade: ela confere a cobertura das
 * empresas relacionadas, inclusive as de colaborador. Aqui se exige, além disso, poder editar colaboradores
 * em alguma empresa onde a pessoa tenha vínculo.
 */
final readonly class UpdateEmployeePersonAction
{
    public function __construct(
        private UpdatePersonAction $updatePerson,
        private ShowEmployeePersonQuery $showEmployeePerson,
        private EffectivePermissions $permissions,
    ) {}

    /**
     * @throws OperationNotAllowed
     * @throws PersonChangeNotAllowed
     * @throws ValidationException
     */
    public function execute(CommercialPersonChangesData $changes, int $personId, Client $account): void
    {
        AuditLog::transaction(function () use ($changes, $personId, $account): void {
            $lockedPerson = Person::where('account_id', $account->id)->lockForUpdate()->findOrFail($personId);

            $editable = $this->permissions->nodesFor(auth()->user(), 'account.employees.update');
            $covered = $this->showEmployeePerson->execute($account->id, $editable, $lockedPerson->id);

            if ($covered === null) {
                throw new OperationNotAllowed;
            }

            if ($this->permissions->nodesFor(auth()->user(), 'account.people.view')->isEmpty()) {
                throw new OperationNotAllowed;
            }

            $this->updatePerson->execute($changes->validatedFor($lockedPerson), $lockedPerson, $account);
        });
    }
}
