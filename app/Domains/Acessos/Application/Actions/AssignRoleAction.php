<?php

namespace App\Domains\Acessos\Application\Actions;

use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Pessoas\Application\Actions\DirectoryAudit;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class AssignRoleAction
{
    public function assertAssignable(int $accountId, int $roleId): Role
    {
        Gate::authorize('account.users.assign-role');
        abort_unless(auth()->user()->account_id === $accountId, 403);
        $role = Role::where('account_id', $accountId)->findOrFail($roleId);
        $allowed = auth()->user()->permissionSlugs();
        abort_if($role->permissions()->whereNotIn('slug', $allowed)->exists(), 403, 'Você não pode atribuir um cargo com permissões que não possui.');

        return $role;
    }

    public function execute(User $user, int $roleId): void
    {
        $role = $this->assertAssignable($user->account_id, $roleId);
        $user->account()->lockForUpdate()->firstOrFail();
        if ($user->role_id === $roleId) {
            return;
        }
        if ($user->role?->system_key === 'administrator' && $role->system_key !== 'administrator' && User::where('account_id', $user->account_id)->where('role_id', $user->role_id)->where('access_enabled', true)->count() <= 1) {
            throw ValidationException::withMessages(['role_id' => 'Mantenha ao menos um administrador da conta.']);
        }
        $before = $user->role_id;
        $user->role_id = $role->id;
        $user->is_account_admin = $role->system_key === 'administrator';
        $user->saveOrFail();
        DirectoryAudit::record($user->account_id, 'user.role-updated', 'user', $user->id, ['before' => ['role_id' => $before], 'after' => ['role_id' => $roleId]]);
    }
}
