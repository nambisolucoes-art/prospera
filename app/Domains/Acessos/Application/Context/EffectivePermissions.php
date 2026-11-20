<?php

namespace App\Domains\Acessos\Application\Context;

use App\Domains\Acessos\Application\Queries\EffectivePermissionSlugsQuery;
use App\Domains\Acessos\Application\Queries\MembershipAccessQuery;
use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Acessos\Domain\DTOs\AuthorizedNodes;
use App\Domains\Acessos\Domain\Models\RoleAssignment;
use App\Models\User;

final class EffectivePermissions
{
    /** @var array<string, array<string, AuthorizedNodes>> */
    private array $permissions = [];

    /** @var array<string,bool> */
    private array $checks = [];

    public function __construct(
        private readonly AccountContext $accounts,
        private readonly EffectivePermissionSlugsQuery $global,
        private readonly MembershipAccessQuery $memberships,
    ) {}

    public function allows(User $user, string $ability, ?AccessNode $node = null): bool
    {
        $nodes = $this->nodesFor($user, $ability);

        if ($node === null) {
            // Autorizações sem alvo explícito continuam reservadas às atribuições de conta.
            return $nodes->tenant;
        }

        if ($node->accountId !== $this->accounts->linkedId($user)) {
            return false;
        }

        if (! $user->access_enabled) {
            return false;
        }

        $key = implode(':', [
            $user->id,
            $node->accountId,
            $node->type,
            $node->companyId,
            $node->establishmentId,
            $ability,
        ]);
        $targets = config('permissions.targets', [])[$ability] ?? [];

        return $this->checks[$key] ??= in_array($node->type, $targets, true) && $nodes->covers($node);
    }

    public function canDelegate(User $user, string $ability, AccessNode $node): bool
    {
        return $node->accountId === $this->accounts->linkedId($user)
            && $this->nodesFor($user, $ability)->covers($node)
            && $node->acceptsPermission($ability);
    }

    public function nodesFor(User $user, string $ability): AuthorizedNodes
    {
        return $this->indexFor($user)[$ability] ?? new AuthorizedNodes;
    }

    /** @return list<string> */
    public function slugsFor(User $user): array
    {
        return array_keys(array_filter(
            $this->indexFor($user),
            fn (AuthorizedNodes $nodes): bool => $nodes->tenant,
        ));
    }

    /** @return list<string> */
    public function visibleSlugsFor(User $user): array
    {
        $slugs = $this->slugsFor($user);

        foreach ([
            'account.audit.list',
            'account.companies.list',
            'account.establishments.list',
            'account.users.list',
            'account.users.create',
            'account.people.list',
            'account.people.view',
            'account.people.create',
            'account.people.update',
            'account.roles.list',
            'account.roles.view',
        ] as $ability) {
            if (! $this->nodesFor($user, $ability)->isEmpty()) {
                $slugs[] = $ability;
            }
        }

        return array_values(array_unique($slugs));
    }

    public function forget(): void
    {
        $this->permissions = [];
        $this->checks = [];
    }

    /** @return array<string, AuthorizedNodes> */
    private function indexFor(User $user): array
    {
        if (! $user->access_enabled) {
            return [];
        }

        $accountId = $this->accounts->linkedId($user);
        $key = implode(':', [$user->id, $accountId, (int) $user->is_saas_admin]);

        return $this->permissions[$key] ??= match (true) {
            $user->is_saas_admin => array_fill_keys($this->global->globalSlugs(['platform', 'self']), new AuthorizedNodes(true)),
            $accountId === null => array_fill_keys($this->global->globalSlugs(['self']), new AuthorizedNodes(true)),
            default => $this->resolveMembership($user->id, $accountId),
        };
    }

    /** @return array<string, AuthorizedNodes> */
    private function resolveMembership(int $userId, int $accountId): array
    {
        $assignments = $this->memberships->assignments($userId, $accountId);
        $roles = array_values($assignments
            ->map(fn (RoleAssignment $assignment): int => $assignment->role_id)
            ->unique()
            ->all());
        $permissions = $this->memberships->permissions($roles, $accountId);
        $coverage = [];

        foreach ($assignments as $assignment) {
            foreach ($permissions[$assignment->role_id] ?? [] as $slug => $_) {
                if ($assignment->scope === 'tenant' || str_starts_with($slug, 'self.')) {
                    $coverage[$slug]['tenant'] = true;
                } elseif ($assignment->company_id !== null && $assignment->scope === 'company') {
                    $coverage[$slug]['companies'][$assignment->company_id] = $assignment->company_id;
                } elseif ($assignment->establishment_id !== null) {
                    $coverage[$slug]['establishments'][$assignment->establishment_id] = $assignment->establishment_id;
                }
            }
        }

        $index = [];

        foreach ($coverage as $slug => $nodes) {
            $index[$slug] = new AuthorizedNodes(
                tenant: $nodes['tenant'] ?? false,
                companies: array_values($nodes['companies'] ?? []),
                establishments: array_values($nodes['establishments'] ?? []),
            );
        }

        return $index;
    }
}
