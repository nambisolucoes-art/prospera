<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Actions;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Application\Data\SaveRoleAssignmentsData;
use App\Domains\Acessos\Application\Queries\AssignmentNodeQuery;
use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Acessos\Domain\Models\RoleAssignment;
use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Auditoria\Application\Actions\AuditContext;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Support\Exceptions\ConflictingChange;
use App\Support\Exceptions\OperationNotAllowed;
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
        if (! ($this->accounts->requireId() === $accountId && $node->accountId === $accountId)) {
            throw new OperationNotAllowed;
        }

        Gate::authorize('account.users.assign-role', $node);

        $role = Role::where('account_id', $accountId)->findOrFail($roleId);
        if (! $role->isAvailableAt($node)) {
            throw new OperationNotAllowed('O cargo não está disponível neste local.');
        }

        $operator = auth()->user();

        foreach ($role->permissions()->pluck('slug') as $slug) {
            if (! $this->permissions->canDelegate($operator, $slug, $node)) {
                throw new OperationNotAllowed('Você não pode gerenciar um cargo com permissões que não possui neste local.');
            }
        }

        return $role;
    }

    public function execute(int $accountId, int $membershipId, SaveRoleAssignmentsData $data): void
    {
        if ($this->accounts->requireId() !== $accountId) {
            throw new OperationNotAllowed;
        }

        $operator = auth()->user();

        if ($data->grants === [] && $data->revocations === []) {
            if ($this->permissions->nodesFor($operator, 'account.users.assign-role')->isEmpty()) {
                throw new OperationNotAllowed;
            }

            throw ValidationException::withMessages(['grants' => 'Informe uma concessão ou revogação de cargo.']);
        }

        AuditLog::transaction(function () use ($accountId, $membershipId, $data, $operator): void {
            Client::whereKey($accountId)->lockForUpdate()->firstOrFail();
            $this->permissions->forget();
            if ($this->permissions->nodesFor($operator, 'account.users.assign-role')->isEmpty()) {
                throw new OperationNotAllowed;
            }

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

                if ($assignment->delete() !== true) {
                    throw new ConflictingChange;
                }
            }
        });

        $this->permissions->forget();
    }
}
