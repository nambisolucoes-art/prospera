<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Queries;

use App\Domains\Acessos\Domain\Models\Permission;

final class EffectivePermissionSlugsQuery
{
    /**
     * @param  list<string>  $scopes
     * @return array<int, string>
     */
    public function globalSlugs(array $scopes): array
    {
        return Permission::where('is_system', true)
            ->whereNull('account_id')
            ->whereIn('scope', $scopes)
            ->pluck('slug')
            ->map(fn ($slug): string => (string) $slug)
            ->all();
    }

    /** @return array<int, string> */
    public function roleSlugs(int $accountId, ?int $roleId): array
    {
        return Permission::where('is_system', true)
            ->whereHas('roles', fn ($role) => $role
                ->where('roles.id', $roleId)
                ->where('roles.account_id', $accountId))
            ->whereIn('scope', ['account', 'self'])
            ->where(fn ($query) => $query
                ->whereNull('account_id')
                ->orWhere('account_id', $accountId))
            ->pluck('slug')
            ->map(fn ($slug): string => (string) $slug)
            ->all();
    }
}
