<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Queries\Companies;

use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use App\Domains\Organizacao\Domain\Models\Company;
use Illuminate\Database\Eloquent\Collection;

final readonly class ListCoveredCompaniesQuery
{
    /**
     * Empresas cobertas por um descritor de alvo empresa (relações comerciais), em ordem de nome.
     *
     * @return Collection<int, Company>
     */
    public function execute(int $accountId, AuthorizedNodes $nodes): Collection
    {
        $query = Company::where('account_id', $accountId);

        if (! $nodes->tenant) {
            $query->whereIn('id', $nodes->companies);
        }

        return $query->orderByRaw('coalesce(trade_name, name)')
            ->orderBy('id')
            ->get(['id', 'code', 'name', 'trade_name', 'active']);
    }
}
