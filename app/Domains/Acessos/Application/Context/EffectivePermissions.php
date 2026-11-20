<?php

namespace App\Domains\Acessos\Application\Context;

use App\Domains\Acessos\Application\Queries\EffectivePermissionSlugsQuery;
use App\Models\User;

final class EffectivePermissions
{
    /** @var array<string, array<string, true>> */
    private array $permissions = [];

    public function __construct(
        private readonly AccountContext $accounts,
        private readonly EffectivePermissionSlugsQuery $query,
    ) {}

    public function allows(User $user, string $ability): bool
    {
        return isset($this->indexFor($user)[$ability]);
    }

    /** @return list<string> */
    public function slugsFor(User $user): array
    {
        return array_keys($this->indexFor($user));
    }

    public function forget(): void
    {
        $this->permissions = [];
    }

    /** @return array<string, true> */
    private function indexFor(User $user): array
    {
        if (! $user->access_enabled) {
            return [];
        }

        $accountId = $this->accounts->linkedId($user);

        // O Gate também aceita sujeitos explícitos, inclusive quando o guard muda em uma inspeção.
        $key = implode(':', [$user->id, $accountId, $user->role_id, (int) $user->is_saas_admin]);

        if (! isset($this->permissions[$key])) {
            $slugs = match (true) {
                $user->is_saas_admin => $this->query->globalSlugs(['platform', 'self']),
                $accountId === null => $this->query->globalSlugs(['self']),
                $this->accounts->activeAccount($user) === null => [],
                default => $this->query->roleSlugs($accountId, $user->role_id),
            };

            $this->permissions[$key] = array_fill_keys($slugs, true);
        }

        return $this->permissions[$key];
    }
}
