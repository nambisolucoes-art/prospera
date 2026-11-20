<?php

namespace App\Domains\Acessos\Application\Queries;

use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Domain\DTOs\AuthorizedNodes;
use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Acessos\Domain\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class MembershipDirectoryQuery
{
    public function __construct(private EffectivePermissions $permissions) {}

    /** @return Builder<AccountMembership> */
    public function visible(int $accountId, AuthorizedNodes $nodes): Builder
    {
        $query = AccountMembership::where('account_memberships.account_id', $accountId);

        if (! $nodes->tenant) {
            $query->whereHas('assignments', fn (Builder $assignments) => $nodes->apply($assignments));
        }

        return $query;
    }

    /** @return LengthAwarePaginator<int, AccountMembership> */
    public function list(int $accountId, AuthorizedNodes $nodes, int $pageSize, string $search): LengthAwarePaginator
    {
        $needle = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

        return $this->visible($accountId, $nodes)
            ->join('people', 'people.id', '=', 'account_memberships.person_id')
            ->join('users', 'users.id', '=', 'account_memberships.user_id')
            ->select('account_memberships.*')
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $fields) => $fields
                ->whereLike('people.name', $needle)
                ->orWhereLike('users.email', $needle)))
            ->with([
                'identity:id,email,profile_photo,access_enabled,password_setup_pending',
                'person:id,name',
                'assignments' => fn ($assignments) => $nodes->apply($assignments->getQuery()),
                'assignments.role:id,name',
                'assignments.company:id,name',
                'assignments.establishment:id,name',
            ])
            ->orderBy('people.name')
            ->orderBy('account_memberships.id')
            ->paginate($pageSize)
            ->withQueryString();
    }

    /** @return list<array<string,mixed>> */
    public function assignmentRows(AccountMembership $membership, AuthorizedNodes $nodes, ?User $operator = null): array
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
            ...$this->assignment($assignment),
            'effective_permissions' => $active ? $assignment->role->permissions
                ->filter(fn ($permission) => $assignment->node()->acceptsPermission($permission->slug))
                ->pluck('name')->values() : [],
            'can_remove' => $operator !== null && $assignment->role->permissions->every(
                fn ($permission) => $this->permissions->canDelegate($operator, $permission->slug, $assignment->node()),
            ),
        ])->all();

        return array_values($rows);
    }

    /** @return array<string,mixed> */
    public function assignment(RoleAssignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'role' => $assignment->role->name,
            'scope' => $assignment->scope,
            'coverage' => match ($assignment->scope) {
                'tenant' => 'Empresas e estabelecimentos da conta',
                'company' => 'Empresa e seus estabelecimentos',
                default => 'Somente este estabelecimento',
            },
            'location' => match ($assignment->scope) {
                'company' => $assignment->company?->name,
                'establishment' => $assignment->establishment?->name,
                default => 'Toda a conta',
            },
        ];
    }
}
