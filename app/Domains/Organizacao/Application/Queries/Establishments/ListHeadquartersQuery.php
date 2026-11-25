<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Queries\Establishments;

use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use App\Domains\Organizacao\Domain\Models\Establishment;
use Illuminate\Database\Eloquent\Collection;

final readonly class ListHeadquartersQuery
{
    /**
     * Matrizes cadastradas das empresas informadas, só nos nós em que a pessoa vê estabelecimentos. A matriz é
     * informação complementar: uma empresa pode administrar somente filiais no Prospera.
     *
     * @param  list<int>  $companyIds
     * @return Collection<int, Establishment>
     */
    public function execute(int $accountId, array $companyIds, AuthorizedNodes $nodes): Collection
    {
        $query = Establishment::where('account_id', $accountId)
            ->whereIn('company_id', $companyIds)
            ->where('is_headquarters', true);

        $nodes->apply($query, 'company_id', 'id');

        return $query->get(['id', 'company_id', 'name', 'trade_name', 'tax_document', 'active']);
    }
}
