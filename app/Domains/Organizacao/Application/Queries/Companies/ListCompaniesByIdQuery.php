<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Queries\Companies;

use App\Domains\Organizacao\Domain\Models\Company;
use Illuminate\Database\Eloquent\Collection;

final readonly class ListCompaniesByIdQuery
{
    /**
     * @param  list<int>  $ids
     * @return Collection<int, Company>
     */
    public function execute(int $accountId, array $ids): Collection
    {
        return Company::where('account_id', $accountId)
            ->whereKey($ids)
            ->orderBy('id')
            ->get();
    }
}
