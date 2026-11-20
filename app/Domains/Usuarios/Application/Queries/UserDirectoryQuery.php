<?php

namespace App\Domains\Usuarios\Application\Queries;

use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class UserDirectoryQuery
{
    /** @return LengthAwarePaginator<int, User> */
    public function list(?Client $account, int $pageSize): LengthAwarePaginator
    {
        return $this->scope($account)
            ->select([
                'users.id',
                'users.person_id',
                'users.name',
                'users.email',
                'users.is_account_admin',
                'users.profile_photo',
                'users.role_id',
            ])
            ->with('role:id,name')
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->paginate($pageSize)
            ->withQueryString();
    }

    public function findWithPhoto(?Client $account, int $userId, string $version): User
    {
        return $this->scope($account)->where('users.profile_photo', $version)
            ->findOrFail($userId, ['users.id', 'users.profile_photo']);
    }

    /** @return Builder<User> */
    private function scope(?Client $account): Builder
    {
        return $account ? $account->users()->getQuery() : User::query()->where('is_saas_admin', true);
    }
}
