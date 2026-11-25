<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Queries\Companies;

use App\Domains\Organizacao\Domain\Models\Company;

final readonly class ShowCompanyQuery
{
    public function execute(int $accountId, int $id): Company
    {
        return Company::where('account_id', $accountId)->findOrFail($id);
    }
}
