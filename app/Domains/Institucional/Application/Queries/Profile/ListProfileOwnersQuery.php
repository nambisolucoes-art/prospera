<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Queries\Profile;

use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use Illuminate\Support\Facades\DB;

/**
 * Empresas e estabelecimentos cujo perfil institucional o operador pode consultar. O descritor é aplicado no SQL:
 * quem tem só uma unidade vê a empresa dela como contexto, sem as outras unidades.
 */
final readonly class ListProfileOwnersQuery
{
    /**
     * @return list<array{code: string, name: string, active: bool, covered: bool, units: list<array{code: string, name: string, active: bool, primary: bool}>}>
     */
    public function execute(int $accountId, AuthorizedNodes $nodes, ?string $companyCode = null): array
    {
        $units = DB::table('establishments')
            ->where('account_id', $accountId)
            ->when($companyCode !== null, fn ($query) => $query->whereIn('company_id', DB::table('companies')
                ->where('account_id', $accountId)->where('code', $companyCode)->select('id')))
            ->when(! $nodes->tenant, fn ($query) => $query->where(fn ($scope) => $scope
                ->whereIn('company_id', $nodes->companies)
                ->orWhereIn('id', $nodes->establishments)))
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get(['id', 'company_id', 'code', 'name', 'active', 'is_primary'])
            ->groupBy('company_id');

        $companies = DB::table('companies')
            ->where('account_id', $accountId)
            ->when($companyCode !== null, fn ($query) => $query->where('code', $companyCode))
            ->when(! $nodes->tenant, fn ($query) => $query->where(fn ($scope) => $scope
                ->whereIn('id', $nodes->companies)
                ->orWhereIn('id', DB::table('establishments')->whereIn('id', $nodes->establishments)->select('company_id'))))
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'trade_name', 'active']);

        return array_values($companies->map(fn (\stdClass $company) => [
            'code' => (string) $company->code,
            'name' => (string) ($company->trade_name ?: $company->name),
            'active' => (bool) $company->active,
            'covered' => $nodes->tenant || in_array((int) $company->id, $nodes->companies, true),
            'units' => array_values($units->get($company->id, collect())->map(fn (\stdClass $unit) => [
                'code' => (string) $unit->code,
                'name' => (string) $unit->name,
                'active' => (bool) $unit->active,
                'primary' => (bool) $unit->is_primary,
            ])->all()),
        ])->all());
    }
}
