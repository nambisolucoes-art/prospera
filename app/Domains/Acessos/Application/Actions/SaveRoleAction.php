<?php

namespace App\Domains\Acessos\Application\Actions;

use App\Domains\Acessos\Application\Data\SaveRoleData;
use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Pessoas\Application\Actions\DirectoryAudit;
use Illuminate\Support\Facades\DB;

final class SaveRoleAction
{
    public function execute(int $accountId, ?Role $role, SaveRoleData $data): void
    {
        AuditLog::transaction(function () use ($accountId, $role, $data) {
            if ($role) {
                $role = Role::where('account_id', $accountId)->lockForUpdate()->findOrFail($role->id);
                abort_if($role->system_key === 'administrator', 403, 'O cargo de administração inicial é protegido.');
            }
            $role ??= new Role(['account_id' => $accountId]);
            $before = $role->exists ? ['name' => $role->name, 'permission_ids' => DB::table('permission_role')->where('role_id', $role->id)->orderBy('permission_id')->pluck('permission_id')->all()] : null;
            $ids = array_map('intval', $data->values['permission_ids'] ?? []);
            sort($ids);
            $allowed = auth()->user()->permissionSlugs();
            abort_if(Permission::whereIn('id', $ids)->whereNotIn('slug', $allowed)->exists(), 403, 'Você não pode conceder permissões que não possui.');
            $role->name = $data->values['name'];
            $role->saveOrFail();
            $role->permissions()->sync($ids);
            AuditLog::relationship($role, 'permission_ids', $before['permission_ids'] ?? [], $ids, 'Permissões');
            $after = ['name' => $role->name, 'permission_ids' => $ids];
            if ($before !== $after) {
                DirectoryAudit::record($accountId, $before ? 'role.updated' : 'role.created', 'role', $role->id, ['before' => $before, 'after' => $after]);
            }
        });
    }
}
