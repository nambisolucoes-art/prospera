<?php

namespace App\Domains\Plataforma\Application\Queries;

use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class AccountInspectionUsersQuery
{
    public function administrator(int $identityId): ?User
    {
        $user = User::where('is_saas_admin', true)
            ->where('access_enabled', true)
            ->whereNotNull('email_verified_at')
            ->find($identityId);

        return $user?->can('platform.inspection.start') ? $user : null;
    }

    /** @return Collection<int, User> */
    public function list(Client $account): Collection
    {
        return $this->eligible($account)
            ->with(['memberships' => fn ($memberships) => $memberships->where('account_id', $account->id)
                ->with('assignments.role:id,name')])
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'profile_photo']);
    }

    public function find(Client $account, int $userId): ?User
    {
        return $this->eligible($account)->find($userId);
    }

    /** @return Builder<User> */
    private function eligible(Client $account): Builder
    {
        return User::whereHas('memberships', fn ($membership) => $membership
            ->where('account_id', $account->id)
            ->where('status', 'active'))
            ->where('access_enabled', true)
            ->where('is_saas_admin', false)
            ->whereNotNull('email_verified_at');
    }
}
