<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Queries\Violations;

use Illuminate\Support\Facades\DB;

/**
 * Detecção, somente leitura, dos invariantes que dependem de várias linhas (seção 6.6).
 */
final readonly class ListPendingOrganizationsQuery
{
    /** @return array<int, string> */
    public function execute(): array
    {
        return DB::table('companies')
            ->whereNull('tax_root')
            ->whereNull('individual_document')
            ->orderBy('account_id')
            ->orderBy('id')
            ->get(['id', 'account_id'])
            ->map(fn ($company) => "Empresa #{$company->id} da conta #{$company->account_id}: sem CNPJ ou CPF.")
            ->values()
            ->all();
    }
}
