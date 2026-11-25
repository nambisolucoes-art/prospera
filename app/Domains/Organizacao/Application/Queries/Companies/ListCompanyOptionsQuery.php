<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Queries\Companies;

use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use App\Domains\Organizacao\Domain\Models\Company;
use Illuminate\Database\Eloquent\Collection;

final readonly class ListCompanyOptionsQuery
{
    /** @return Collection<int, Company> */
    public function execute(int $accountId, ?AuthorizedNodes $unitNodes = null): Collection
    {
        $query = Company::where('account_id', $accountId);

        if ($unitNodes !== null && ! $unitNodes->tenant) {
            $query->where(fn ($company) => $company->whereIn('id', $unitNodes->companies)
                ->orWhereHas('establishments', fn ($unit) => $unit->whereIn('id', $unitNodes->establishments)));
        }

        return $query->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'trade_name', 'person_type', 'tax_root', 'individual_document', 'active']);
    }
}
