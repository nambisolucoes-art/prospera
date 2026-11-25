<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Queries;

use Illuminate\Support\Facades\DB;

/**
 * Detecção, somente leitura, dos invariantes que dependem de várias linhas (seção 6.6).
 */
final class OrganizationViolationsQuery
{
    /** @return list<string> */
    public function violations(): array
    {
        $violations = [];

        $accountsWithoutCompany = DB::table('clients')
            ->whereNotExists(fn ($query) => $query
                ->from('companies')
                ->whereColumn('companies.account_id', 'clients.id'))
            ->orderBy('id')
            ->pluck('id');

        foreach ($accountsWithoutCompany as $id) {
            $violations[] = "Conta #{$id}: sem empresa.";
        }

        $primaries = DB::table('companies')
            ->leftJoin('establishments', function ($join) {
                $join->on('establishments.company_id', '=', 'companies.id')
                    ->where('establishments.is_primary', true);
            })
            ->groupBy('companies.id')
            ->havingRaw('count(establishments.id) <> 1')
            ->orderBy('companies.id')
            ->selectRaw('companies.id, count(establishments.id) as total')
            ->get();

        foreach ($primaries as $company) {
            $violations[] = "Empresa #{$company->id}: {$company->total} estabelecimentos principais (esperado 1).";
        }

        $inactivePrimaries = DB::table('establishments')
            ->where('is_primary', true)
            ->where('active', false)
            ->orderBy('id')
            ->pluck('id');

        foreach ($inactivePrimaries as $id) {
            $violations[] = "Estabelecimento #{$id}: principal inativo.";
        }

        $activeWithoutUnits = DB::table('companies')
            ->where('active', true)
            ->whereNotExists(fn ($query) => $query
                ->from('establishments')
                ->whereColumn('establishments.company_id', 'companies.id')
                ->where('establishments.active', true))
            ->orderBy('id')
            ->pluck('id');

        foreach ($activeWithoutUnits as $id) {
            $violations[] = "Empresa #{$id}: ativa sem estabelecimento ativo.";
        }

        $headquarters = DB::table('establishments')
            ->where('is_headquarters', true)
            ->groupBy('company_id')
            ->havingRaw('count(*) > 1')
            ->orderBy('company_id')
            ->pluck('company_id');

        foreach ($headquarters as $id) {
            $violations[] = "Empresa #{$id}: mais de uma matriz.";
        }

        $foreignDocuments = DB::table('establishments')
            ->join('companies', 'companies.id', '=', 'establishments.company_id')
            ->where(fn ($query) => $query
                ->where(fn ($document) => $document
                    ->whereNotNull('establishments.tax_document')
                    ->where(fn ($root) => $root
                        ->whereNull('companies.tax_root')
                        ->orWhereRaw('substr(establishments.tax_document, 1, 8) <> companies.tax_root')))
                ->orWhere(fn ($headquarters) => $headquarters
                    ->where('establishments.is_headquarters', true)
                    ->whereNull('companies.tax_root')))
            ->orderBy('establishments.id')
            ->pluck('establishments.id');

        foreach ($foreignDocuments as $id) {
            $violations[] = "Estabelecimento #{$id}: CNPJ ou matriz fora da raiz da empresa.";
        }

        $foreignAccounts = DB::table('establishments')
            ->join('companies', 'companies.id', '=', 'establishments.company_id')
            ->whereColumn('establishments.account_id', '<>', 'companies.account_id')
            ->orderBy('establishments.id')
            ->pluck('establishments.id');

        foreach ($foreignAccounts as $id) {
            $violations[] = "Estabelecimento #{$id}: conta diferente da empresa.";
        }

        return $violations;
    }

    /** @return array<int, string> */
    public function pending(): array
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
