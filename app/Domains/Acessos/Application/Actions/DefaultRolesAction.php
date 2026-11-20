<?php

namespace App\Domains\Acessos\Application\Actions;

use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Auditoria\Application\Actions\AuditLog;

final class DefaultRolesAction
{
    /** @return array{Role, Role} */
    public function execute(int $accountId): array
    {
        $admin = Role::firstOrCreate(['account_id' => $accountId, 'system_key' => 'administrator'], ['name' => 'Administrador da conta']);
        $member = Role::firstOrCreate(['account_id' => $accountId, 'system_key' => 'member'], ['name' => 'Usuário']);
        $adminBefore = $admin->permissions()->pluck('permissions.id')->all();
        $memberBefore = $member->permissions()->pluck('permissions.id')->all();
        $admin->permissions()->syncWithoutDetaching(Permission::where('is_system', true)->whereNull('account_id')->whereIn('scope', ['account', 'self'])->pluck('id')->all());
        if ($member->wasRecentlyCreated) {
            $member->permissions()->sync(Permission::where('is_system', true)->whereNull('account_id')->where(fn ($q) => $q->where('scope', 'self')->orWhereIn('slug', ['account.dashboard.view', 'account.points.list']))->pluck('id')->all());
        }

        AuditLog::relationship($admin, 'permission_ids', $adminBefore, $admin->permissions()->pluck('permissions.id')->all(), 'Permissões');
        AuditLog::relationship($member, 'permission_ids', $memberBefore, $member->permissions()->pluck('permissions.id')->all(), 'Permissões');

        return [$admin, $member];
    }
}
