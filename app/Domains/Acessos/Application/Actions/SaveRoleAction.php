<?php

namespace App\Domains\Acessos\Application\Actions;

use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Application\Data\RoleAvailabilityData;
use App\Domains\Acessos\Application\Data\SaveRoleData;
use App\Domains\Acessos\Application\Queries\RoleAvailabilityQuery;
use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Plataforma\Domain\Models\Client;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class SaveRoleAction
{
    public function __construct(private RoleAvailabilityQuery $availability, private EffectivePermissions $permissions) {}

    public function execute(int $accountId, ?Role $role, SaveRoleData $data, RoleAvailabilityData $availability): void
    {
        AuditLog::transaction(function () use ($accountId, $role, $data, $availability): void {
            Client::whereKey($accountId)->lockForUpdate()->firstOrFail();
            $this->permissions->forget();

            if ($role === null) {
                Gate::authorize('account.roles.create');
            } else {
                Gate::authorize('account.roles.update');
            }

            $lockedRole = $role === null
                ? new Role(['account_id' => $accountId])
                : Role::where('account_id', $accountId)
                    ->lockForUpdate()
                    ->findOrFail($role->id);

            abort_if(
                $lockedRole->system_key === 'administrator',
                403,
                'O cargo de administração inicial é protegido.',
            );

            $before = $lockedRole->exists ? $lockedRole->permissionIds() : [];

            $permissionIds = array_map('intval', $data->values['permission_ids'] ?? []);
            sort($permissionIds);

            $grantsMissingPermission = Permission::whereIn('id', $permissionIds)
                ->whereNotIn('slug', auth()->user()->permissionSlugs())
                ->exists();

            abort_if($grantsMissingPermission, 403, 'Você não pode conceder permissões que não possui.');

            if ($lockedRole->system_key !== null && $availability->mode !== 'tenant') {
                throw ValidationException::withMessages(['availability' => 'Cargos de sistema abrangem toda a conta.']);
            }

            if ($lockedRole->exists) {
                $affected = $this->availability->uncovered($lockedRole, $availability)->with('membership')->orderBy('id')->get();

                if ($affected->modelKeys() !== $availability->confirmedRevocations) {
                    throw ValidationException::withMessages([
                        'availability' => 'Revise e confirme as atribuições afetadas pela alteração de abrangência.',
                    ]);
                }

                $canRemove = ! $lockedRole->permissions()
                    ->whereNotIn('slug', auth()->user()->permissionSlugs())
                    ->exists();
                abort_if($affected->isNotEmpty() && ! $canRemove, 403);

                foreach ($affected as $assignment) {
                    $assignment->delete();
                }

                foreach ($affected->pluck('membership')->unique('id') as $membership) {
                    $membership->syncLegacyRole();
                }
            }

            $lockedRole->name = $data->values['name'];
            $lockedRole->availability = $availability->mode;
            $lockedRole->saveOrFail();

            $nodes = [];

            if ($availability->mode === 'restricted') {
                foreach ($availability->companies as $companyId) {
                    $nodes[] = ['company_id' => $companyId, 'establishment_id' => null];
                }

                foreach ($this->availability->selectedUnits($accountId, $availability) as $unit) {
                    if (! in_array($unit->company_id, $availability->companies, true)) {
                        $nodes[] = ['company_id' => $unit->company_id, 'establishment_id' => $unit->id];
                    }
                }
            }

            $lockedRole->syncAvailabilityNodes($nodes);
            $lockedRole->permissions()->sync($permissionIds);
            $lockedRole->recordAuditFieldChange('permission_ids', $before, $permissionIds, 'Permissões');
        });

        $this->permissions->forget();
    }
}
