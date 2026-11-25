<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Queries\Permissions;

use App\Domains\Acessos\Domain\Models\Permission;

final readonly class ListGlobalPermissionSlugsQuery
{
    /**
     * @param  list<string>  $scopes
     * @return array<int, string>
     */
    public function execute(array $scopes): array
    {
        return Permission::where('is_system', true)
            ->whereNull('account_id')
            ->whereIn('scope', $scopes)
            ->pluck('slug')
            ->map(fn ($slug): string => (string) $slug)
            ->all();
    }
}
