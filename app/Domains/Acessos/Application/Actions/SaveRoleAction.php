<?php

namespace App\Domains\Acessos\Application\Actions;

use App\Domains\Acessos\Application\Data\SaveRoleData;
use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Auditoria\Application\Actions\AuditLog;

final class SaveRoleAction
{
    public function execute(int $accountId, ?Role $role, SaveRoleData $data): void
    {
        AuditLog::transaction(function () use ($accountId, $role, $data): void {
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

            $lockedRole->name = $data->values['name'];
            $lockedRole->saveOrFail();

            $lockedRole->permissions()->sync($permissionIds);
            $lockedRole->recordAuditFieldChange('permission_ids', $before, $permissionIds, 'Permissões');
        });
    }
}
