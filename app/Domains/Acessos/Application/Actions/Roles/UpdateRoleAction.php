<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Actions\Roles;

use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Application\Data\Roles\UpdateRoleData;
use App\Domains\Acessos\Application\Queries\Roles\ListUncoveredAssignmentsQuery;
use App\Domains\Acessos\Domain\DTOs\Roles\UpdateRoleDTO;
use App\Domains\Acessos\Domain\Exceptions\RoleChangeNotAllowed;
use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Plataforma\Domain\Models\Client;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Altera um cargo da conta, a abrangência e as atribuições que ela deixa de cobrir. */
final readonly class UpdateRoleAction
{
    public function __construct(
        private SyncRoleAvailabilityAction $syncAvailability,
        private ListUncoveredAssignmentsQuery $listUncoveredAssignments,
        private EffectivePermissions $permissions,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws RoleChangeNotAllowed
     * @throws ValidationException
     */
    public function execute(UpdateRoleData $data, Role $role): void
    {
        AuditLog::transaction(function () use ($data, $role): void {
            Client::whereKey($role->account_id)->lockForUpdate()->firstOrFail();
            $this->permissions->forget();

            Gate::authorize('account.roles.update');

            $lockedRole = Role::where('account_id', $role->account_id)
                ->lockForUpdate()
                ->findOrFail($role->id);

            if ($lockedRole->system_key === 'administrator') {
                throw RoleChangeNotAllowed::protectedRole();
            }

            $before = $lockedRole->permissionIds();

            $grantsMissingPermission = Permission::whereIn('id', $data->permissionIds)
                ->whereNotIn('slug', auth()->user()->permissionSlugs())
                ->exists();

            if ($grantsMissingPermission) {
                throw RoleChangeNotAllowed::permissionsNotHeld();
            }

            if ($lockedRole->system_key !== null && $data->availability->mode !== 'tenant') {
                throw ValidationException::withMessages(['availability' => 'Cargos de sistema abrangem toda a conta.']);
            }

            $affected = $this->listUncoveredAssignments->execute($lockedRole, $data->availability)->orderBy('id')->get();

            if ($affected->modelKeys() !== $data->availability->confirmedRevocations) {
                throw ValidationException::withMessages([
                    'availability' => 'Revise e confirme as atribuições afetadas pela alteração de abrangência.',
                ]);
            }

            $canRemove = ! $lockedRole->permissions()
                ->whereNotIn('slug', auth()->user()->permissionSlugs())
                ->exists();

            if ($affected->isNotEmpty() && ! $canRemove) {
                throw RoleChangeNotAllowed::assignmentsOutOfReach();
            }

            foreach ($affected as $assignment) {
                $assignment->delete();
            }

            $roleDTO = new UpdateRoleDTO(
                name: $data->name,
                availability: $data->availability->mode,
            );

            $lockedRole->revise($roleDTO);
            $lockedRole->saveOrFail();

            $this->syncAvailability->execute($lockedRole, $data->availability);
            $lockedRole->permissions()->sync($data->permissionIds);
            $lockedRole->recordAuditFieldChange('permission_ids', $before, $data->permissionIds, 'Permissões');
        });

        $this->permissions->forget();
    }
}
