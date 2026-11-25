<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Queries\Companies;

use App\Domains\Organizacao\Domain\Models\Company;

final readonly class AccountHasSingleCompanyQuery
{
    // Com uma empresa só, as telas omitem a escolha de empresa (contexto organizacional, Empresa pequena).
    public function execute(int $accountId): bool
    {
        return Company::where('account_id', $accountId)->limit(2)->count() === 1;
    }
}
