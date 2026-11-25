<?php

declare(strict_types=1);

namespace App\Domains\Usuarios\Application\Queries\Users;

use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final readonly class ShowUserWithPhotoQuery
{
    /** @return Builder<User> */
    private function scope(?Client $account): Builder
    {
        return $account
            ? User::whereHas('memberships', fn ($membership) => $membership->where('account_id', $account->id))
            : User::query()->where('is_saas_admin', true);
    }

    public function execute(?Client $account, int $userId, string $version): User
    {
        return $this->scope($account)->where('users.profile_photo', $version)
            ->findOrFail($userId, ['users.id', 'users.profile_photo']);
    }
}
