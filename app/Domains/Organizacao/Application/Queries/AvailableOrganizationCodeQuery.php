<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Queries;

use App\Domains\Organizacao\Domain\DTOs\OrganizationCode;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;

final class AvailableOrganizationCodeQuery
{
    public function forCompany(int $accountId, string $name): string
    {
        $base = OrganizationCode::suggestFrom($name);

        $taken = Company::where('account_id', $accountId)
            ->whereLike('code', $base.'%')
            ->pluck('code')
            ->all();

        return OrganizationCode::firstAvailable($base, $taken);
    }

    public function forEstablishment(int $accountId, string $name): string
    {
        $base = OrganizationCode::suggestFrom($name);

        $taken = Establishment::where('account_id', $accountId)
            ->whereLike('code', $base.'%')
            ->pluck('code')
            ->all();

        return OrganizationCode::firstAvailable($base, $taken);
    }
}
