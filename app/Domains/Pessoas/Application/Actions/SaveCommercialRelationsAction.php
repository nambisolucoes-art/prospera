<?php

namespace App\Domains\Pessoas\Application\Actions;

use App\Domains\Clientes\Application\Actions\SaveCustomerAction;
use App\Domains\Fornecedores\Application\Actions\SaveSupplierAction;
use App\Domains\Organizacao\Application\Queries\CompanyDirectoryQuery;
use App\Domains\Pessoas\Application\Data\CommercialRelationsData;
use App\Domains\Pessoas\Domain\Models\Person;

/**
 * Aplica as escolhas de Cliente e Fornecedor de cada empresa informada, sem tocar nas demais empresas.
 *
 * O chamador já travou a pessoa dentro de AuditLog::transaction().
 */
final readonly class SaveCommercialRelationsAction
{
    public function __construct(
        private SaveCustomerAction $saveCustomer,
        private SaveSupplierAction $saveSupplier,
        private CompanyDirectoryQuery $companies,
    ) {}

    public function execute(Person $lockedPerson, CommercialRelationsData $relations): void
    {
        if ($relations->choices === []) {
            return;
        }

        $companies = $this->companies
            ->findMany($lockedPerson->account_id, array_keys($relations->choices))
            ->keyBy('id');

        foreach ($relations->choices as $companyId => $choice) {
            $company = $companies->get($companyId);
            abort_if($company === null, 404);

            if (array_key_exists('customer', $choice)) {
                $this->saveCustomer->execute($lockedPerson, $company, $choice['customer']);
            }

            if (array_key_exists('supplier', $choice)) {
                $this->saveSupplier->execute($lockedPerson, $company, $choice['supplier']);
            }
        }
    }
}
