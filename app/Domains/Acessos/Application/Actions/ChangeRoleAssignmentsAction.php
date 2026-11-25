<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Actions;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Application\Data\ChangeRoleAssignmentsData;
use App\Domains\Acessos\Application\Queries\AssignmentNodeQuery;
use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Acessos\Domain\Models\RoleAssignment;
use App\Domains\Auditoria\Application\Actions\AuditContext;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Support\Exceptions\ConflictingChange;
use App\Support\Exceptions\OperationNotAllowed;
use Illuminate\Validation\ValidationException;

final readonly class ChangeRoleAssignmentsAction
{
    public function __construct(
        private AccountContext $accounts,
        private EffectivePermissions $permissions,
        private AssignmentNodeQuery $nodes,
        private AssertRoleAssignableAction $assertAssignable,
        private AuditContext $audit,
    ) {}

    public function execute(ChangeRoleAssignmentsData $data, int $accountId, int $membershipId): void
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

                $role = $this->assertAssignable->execute($accountId, (int) $grant['role_id'], $node);
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

                $role = $this->assertAssignable->execute($accountId, $assignment->role_id, $node);

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
