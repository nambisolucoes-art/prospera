<?php

namespace App\Domains\Pessoas\Application\Actions;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Clientes\Application\Actions\SaveCustomerAction;
use App\Domains\Fornecedores\Application\Actions\SaveSupplierAction;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Pessoas\Domain\Enums\CommercialRole;
use App\Domains\Pessoas\Domain\Models\Person;
use Illuminate\Validation\ValidationException;

/**
 * Vincula uma pessoa existente a uma empresa, ou ativa e inativa a relação, nas telas de Clientes e Fornecedores.
 *
 * A empresa vem da URL de contexto. A pessoa é travada para que envios simultâneos não criem relações repetidas;
 * o índice único (company_id, person_id) é a última barreira.
 */
final readonly class SetCommercialRelationAction
{
    public function __construct(
        private SaveCustomerAction $saveCustomer,
        private SaveSupplierAction $saveSupplier,
    ) {}

    public function execute(CommercialRole $role, Company $company, int $personId, bool $active, bool $link): void
    {
        AuditLog::transaction(function () use ($role, $company, $personId, $active, $link): void {
            $lockedPerson = Person::where('account_id', $company->account_id)
                ->lockForUpdate()
                ->findOrFail($personId);

            $exists = $role->model()::where('account_id', $company->account_id)
                ->where('company_id', $company->id)
                ->where('person_id', $lockedPerson->id)
                ->exists();

            if ($link && $exists) {
                throw ValidationException::withMessages([
                    'person_id' => 'Esta pessoa já tem relação de '.mb_strtolower($role->label())
                        .' com esta empresa. Use Ativar na listagem se ela estiver inativa.',
                ]);
            }

            if (! $link && ! $exists) {
                abort(404);
            }

            if ($role === CommercialRole::Customer) {
                $this->saveCustomer->execute($lockedPerson, $company, $active);
            } else {
                $this->saveSupplier->execute($lockedPerson, $company, $active);
            }
        });
    }
}
