<?php

namespace App\Domains\Acessos\Application\Queries;

use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use Illuminate\Database\Eloquent\Builder;

final class AccessDirectoryQuery
{
    /** @return Builder<Permission> */
    public function permissions(int $accountId): Builder
    {
        return Permission::where('is_system', true)->whereIn('scope', ['account', 'self'])->where(fn ($q) => $q->whereNull('account_id')->orWhere('account_id', $accountId))->orderBy('module')->orderBy('name')->orderBy('id');
    }

    /** @return Builder<Role> */
    public function roles(int $accountId): Builder
    {
        return Role::where('account_id', $accountId)->orderBy('name')->orderBy('id');
    }
}
