<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Queries\Roles;

use App\Domains\Acessos\Domain\Models\Role;
use Illuminate\Database\Eloquent\Builder;

final readonly class ListRolesQuery
{
    /** @return Builder<Role> */
    public function execute(int $accountId): Builder
    {
        return Role::where('account_id', $accountId)->orderBy('name')->orderBy('id');
    }
}
