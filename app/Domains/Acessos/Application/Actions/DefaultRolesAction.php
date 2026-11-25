<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Actions;

use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;

final readonly class DefaultRolesAction
{
    /** @return array{Role, Role} */
    public function execute(int $accountId): array
    {
        $admin = Role::firstOrCreate(
            ['account_id' => $accountId, 'system_key' => 'administrator'],
            ['name' => 'Administrador da conta'],
        );
        $member = Role::firstOrCreate(
            ['account_id' => $accountId, 'system_key' => 'member'],
            ['name' => 'Usuário'],
        );

        $adminBefore = $admin->permissionIds();
        $memberBefore = $member->permissionIds();

        // O administrador recebe toda permissão de conta e pessoal, inclusive as novas.
        $accountAndSelfIds = Permission::where('is_system', true)
            ->whereNull('account_id')
            ->whereIn('scope', ['account', 'self'])
            ->pluck('id')
            ->all();

        $admin->permissions()->syncWithoutDetaching($accountAndSelfIds);

        if ($member->wasRecentlyCreated) {
            $memberIds = Permission::where('is_system', true)
                ->whereNull('account_id')
                ->where(fn ($query) => $query
                    ->where('scope', 'self')
                    ->orWhereIn('slug', ['account.dashboard.view', 'account.establishments.list']))
                ->pluck('id')
                ->all();

            $member->permissions()->sync($memberIds);
        }

        $admin->recordAuditFieldChange('permission_ids', $adminBefore, $admin->permissionIds(), 'Permissões');
        $member->recordAuditFieldChange('permission_ids', $memberBefore, $member->permissionIds(), 'Permissões');

        return [$admin, $member];
    }
}
