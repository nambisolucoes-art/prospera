comm<?php

declare(strict_types=1);

namespace App\Domains\Usuarios\Application\Queries\Users;

use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListUsersQuery
{
    /** @return Builder<User> */
    private function scope(?Client $account): Builder
    {
        return $account
            ? User::whereHas('memberships', fn ($membership) => $membership->where('account_id', $account->id))
            : User::query()->where('is_saas_admin', true);
    }

    /** @return LengthAwarePaginator<int, User> */
    public function execute(?Client $account, int $pageSize): LengthAwarePaginator
    {
        return $this->scope($account)
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.profile_photo',
            ])
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->paginate($pageSize)
            ->withQueryString();
    }
}
