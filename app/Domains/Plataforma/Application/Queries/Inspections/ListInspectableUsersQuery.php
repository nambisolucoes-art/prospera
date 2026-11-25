<?php

declare(strict_types=1);

namespace App\Domains\Plataforma\Application\Queries\Inspections;

use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final readonly class ListInspectableUsersQuery
{
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

    /** @return Collection<int, User> */
    public function execute(Client $account): Collection
    {
        return $this->eligible($account)
            ->with(['memberships' => fn ($memberships) => $memberships->where('account_id', $account->id)
                ->with('assignments.role:id,name')])
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'profile_photo']);
    }
}
