<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Queries\Companies;

use App\Domains\Organizacao\Domain\Models\Company;
use App\Support\Exceptions\ResourceNotAvailable;
use Illuminate\Database\Eloquent\Collection;

final readonly class LockActiveCompaniesByCodeQuery
{
    /**
     * @param  list<string>  $codes
     * @return Collection<int, Company>
     */
    public function execute(int $accountId, array $codes): Collection
    {
        if ($codes === []) {
            return new Collection;
        }

        $companies = Company::where('account_id', $accountId)
            ->whereIn('code', $codes)
            ->where('active', true)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($companies->count() !== count($codes)) {
            throw new ResourceNotAvailable;
        }

        return $companies;
    }
}
