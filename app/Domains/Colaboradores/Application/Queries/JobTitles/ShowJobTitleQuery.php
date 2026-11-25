<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Queries\JobTitles;

use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use App\Domains\Colaboradores\Domain\Models\JobTitle;

/** Lê uma função dentro da conta e das empresas que o descritor da permissão autoriza. */
final readonly class ShowJobTitleQuery
{
    public function execute(int $accountId, AuthorizedNodes $nodes, int $id): ?JobTitle
    {
        $query = JobTitle::query()
            ->with('company:id,code,name,trade_name')
            ->where('account_id', $accountId)
            ->whereKey($id);

        $nodes->apply($query, 'company_id', null);

        return $query->first();
    }
}
