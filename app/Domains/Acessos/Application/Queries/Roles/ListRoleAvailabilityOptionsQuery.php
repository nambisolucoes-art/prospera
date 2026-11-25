<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Queries\Roles;

use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use Illuminate\Database\Eloquent\Collection;

final readonly class ListRoleAvailabilityOptionsQuery
{
    /** @return array{companies:Collection<int, Company>,establishments:Collection<int, Establishment>} */
    public function execute(int $accountId): array
    {
        return [
            'companies' => Company::where('account_id', $accountId)
                ->orderBy('name')
                ->get(['id', 'name', 'active']),
            'establishments' => Establishment::where('account_id', $accountId)
                ->orderBy('name')
                ->get(['id', 'company_id', 'name', 'active']),
        ];
    }
}
