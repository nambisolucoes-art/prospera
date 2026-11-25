<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Queries\Memberships;

use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Acessos\Domain\Models\RoleAssignment;
use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use App\Models\User;

final readonly class ListMembershipAssignmentsQuery
{
    public function __construct(
        private EffectivePermissions $permissions,
        private ShowAssignmentQuery $showAssignment,
    ) {}

    /** @return list<array<string,mixed>> */
    public function execute(AccountMembership $membership, AuthorizedNodes $nodes, ?User $operator = null): array
    {
        $assignments = $membership->assignments()->with([
            'role:id,name',
            'role.permissions',
            'company:id,name',
            'establishment:id,name',
        ]);
        $nodes->apply($assignments->getQuery());
        $active = $membership->status === 'active' && $membership->identity()->where('access_enabled', true)->exists();

        $rows = $assignments->get()->map(fn (RoleAssignment $assignment): array => [
            ...$this->showAssignment->execute($assignment),
            'effective_permissions' => $active ? $assignment->role->permissions
                ->filter(fn ($permission) => $assignment->node()->acceptsPermission($permission->slug))
                ->pluck('name')->values() : [],
            'can_remove' => $operator !== null && $assignment->role->permissions->every(
                fn ($permission) => $this->permissions->canDelegate($operator, $permission->slug, $assignment->node()),
            ),
        ])->all();

        return array_values($rows);
    }
}
