<?php

declare(strict_types=1);

namespace App\Domains\Plataforma\Application\Queries\Inspections;

use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final readonly class ShowInspectableUserQuery
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

    public function execute(Client $account, int $userId): ?User
    {
        return $this->eligible($account)->find($userId);
    }
}
