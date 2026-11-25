<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Queries\Codes;

use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\ValueObjects\OrganizationCode;

final readonly class AvailableCompanyCodeQuery
{
    public function execute(int $accountId, string $name): string
    {
        $base = OrganizationCode::suggestFrom($name);

        $taken = Company::where('account_id', $accountId)
            ->whereLike('code', $base.'%')
            ->pluck('code')
            ->all();

        return OrganizationCode::firstAvailable($base, $taken);
    }
}
