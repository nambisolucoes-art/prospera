<?php

namespace App\Domains\Plataforma\Application\Queries;

use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class AccountInspectionUsersQuery
{
    /** @return Collection<int, User> */
    public function list(Client $account): Collection
    {
        return $this->eligible($account)->with('role:id,name')->orderBy('name')->orderBy('id')->get(['id', 'name', 'email', 'is_account_admin', 'profile_photo', 'role_id']);
    }

    public function find(Client $account, int $userId): ?User
    {
        return $this->eligible($account)->find($userId);
    }

    /** @return Builder<User> */
    private function eligible(Client $account): Builder
    {
        return $account->users()->getQuery()->where('access_enabled', true)->where('is_saas_admin', false)->whereNotNull('email_verified_at');
    }
}
