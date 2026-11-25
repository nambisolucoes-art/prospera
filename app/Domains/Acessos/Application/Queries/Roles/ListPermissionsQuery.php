<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Queries\Roles;

use App\Domains\Acessos\Domain\Models\Permission;
use Illuminate\Database\Eloquent\Builder;

final readonly class ListPermissionsQuery
{
    /** @return Builder<Permission> */
    public function execute(int $accountId): Builder
    {
        return Permission::where('is_system', true)
            ->whereIn('scope', ['account', 'self'])
            ->where(fn ($query) => $query
                ->whereNull('account_id')
                ->orWhere('account_id', $accountId))
            ->orderBy('module')
            ->orderBy('name')
            ->orderBy('id');
    }
}
