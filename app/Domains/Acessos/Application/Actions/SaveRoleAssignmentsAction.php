<?php

namespace App\Domains\Acessos\Application\Actions;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Application\Data\SaveRoleAssignmentsData;
use App\Domains\Acessos\Application\Queries\AssignmentNodeQuery;
use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Acessos\Domain\Models\RoleAssignment;
use App\Domains\Auditoria\Application\Actions\AuditContext;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class SaveRoleAssignmentsAction
{
    public function __construct(
        private AccountContext $accounts,
        private EffectivePermissions $permissions,
        private AssignmentNodeQuery $nodes,
        private AuditContext $audit,
    ) {}

    public function assertAssignable(int $accountId, int $roleId, ?AccessNode $node = null): Role
    {
        $node ??= AccessNode::tenant($accountId);
        abort_unless($this->accounts->requireId() === $accountId && $node->accountId === $accountId, 403);

        Gate::authorize('account.users.assign-role', $node);

        $role = Role::where('account_id', $accountId)->findOrFail($roleId);
        abort_unless($role->isAvailableAt($node), 403, 'O cargo não está disponível neste local.');

        $operator = auth()->user();

        foreach ($role->permissions()->pluck('slug') as $slug) {
            abort_unless(
                $this->permissions->canDelegate($operator, $slug, $node),
                403,
                'Você não pode gerenciar um cargo com permissões que não possui neste local.',
            );
        }

        return $role;
    }

    public function execute(int $accountId, int $membershipId, SaveRoleAssignmentsData $data): void
    {
        abort_unless($this->accounts->requireId() === $accountId, 403);

        AuditLog::transaction(function () use ($accountId, $membershipId, $data): void {
            Client::whereKey($accountId)->lockForUpdate()->firstOrFail();
            $this->permissions->forget();

            $membership = AccountMembership::where('account_id', $accountId)
                ->lockForUpdate()
                ->findOrFail($membershipId);

            foreach ($data->grants as $grant) {
                $node = $this->nodes->resolve(
                    $accountId,
                    $grant['scope'],
                    isset($grant['company_id']) ? (int) $grant['company_id'] : null,
                    isset($grant['establishment_id']) ? (int) $grant['establishment_id'] : null,
                    granting: true,
                );

                $role = $this->assertAssignable($accountId, (int) $grant['role_id'], $node);
                $duplicate = $membership->assignments()
                    ->where('role_id', $role->id)
                    ->where('scope', $node->type)
                    ->where('company_id', $node->companyId)
                    ->where('establishment_id', $node->establishmentId)
                    ->exists();

                if ($duplicate) {
                    throw ValidationException::withMessages(['grants' => 'O cargo já está atribuído neste local.']);
                }

                RoleAssignment::grant($membership, $role, $node, $this->audit->actorId ?? auth()->user()?->id)->saveOrFail();
            }

            foreach ($data->revocations as $id) {
                $assignment = $membership->assignments()->findOrFail($id);
                $node = $this->nodes->resolve(
                    $accountId,
                    $assignment->scope,
                    $assignment->company_id,
                    $assignment->establishment_id,
                    granting: false,
                );

                $role = $this->assertAssignable($accountId, $assignment->role_id, $node);

                if ($role->system_key === 'administrator' && $membership->isLastActiveAdministrator()) {
                    throw ValidationException::withMessages(['role_id' => 'Mantenha ao menos um administrador da conta.']);
                }

                abort_unless($assignment->delete() === true, 409);
            }

            $membership->syncLegacyRole();
        });

        $this->permissions->forget();
    }

    public function replaceTenant(User $user, int $roleId): void
    {
        $accountId = $this->accounts->requireId();
        AuditLog::transaction(function () use ($accountId, $user, $roleId): void {
            Client::whereKey($accountId)->lockForUpdate()->firstOrFail();
            $membership = AccountMembership::where('account_id', $accountId)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $tenantAssignments = $membership->assignments()->where('scope', 'tenant')->get();

            if ($tenantAssignments->contains('role_id', $roleId)) {
                return;
            }

            $data = new SaveRoleAssignmentsData(
                grants: [['role_id' => $roleId, 'scope' => 'tenant']],
                revocations: array_values($tenantAssignments->map(fn (RoleAssignment $assignment): int => $assignment->id)->all()),
            );

            $this->execute($accountId, $membership->id, $data);
        });
    }
}
