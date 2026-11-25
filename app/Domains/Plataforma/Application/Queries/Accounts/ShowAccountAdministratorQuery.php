<?php

declare(strict_types=1);

namespace App\Domains\Plataforma\Application\Queries\Accounts;

use App\Models\User;

final readonly class ShowAccountAdministratorQuery
{
    public function execute(int $accountId): ?User
    {
        return User::where('access_enabled', true)
            ->whereHas('memberships', fn ($membership) => $membership
                ->where('account_id', $accountId)
                ->where('status', 'active')
                ->whereHas('assignments', fn ($assignment) => $assignment
                    ->where('scope', 'tenant')
                    ->whereHas('role', fn ($role) => $role->where('system_key', 'administrator'))))
            ->orderBy('id')
            ->first(['name', 'email']);
    }
}
