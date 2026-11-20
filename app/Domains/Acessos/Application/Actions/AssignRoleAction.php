<?php

namespace App\Domains\Acessos\Application\Actions;

use App\Domains\Acessos\Domain\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class AssignRoleAction
{
    public function assertAssignable(int $accountId, int $roleId): Role
    {
        Gate::authorize('account.users.assign-role');

        $operator = auth()->user();
        abort_unless($operator->account_id === $accountId, 403);

        $role = Role::where('account_id', $accountId)->findOrFail($roleId);

        $grantsMissingPermission = $role->permissions()
            ->whereNotIn('slug', $operator->permissionSlugs())
            ->exists();

        abort_if(
            $grantsMissingPermission,
            403,
            'Você não pode atribuir um cargo com permissões que não possui.',
        );

        return $role;
    }

    public function execute(User $user, int $roleId): void
    {
        $role = $this->assertAssignable($user->account_id, $roleId);

        $user->account()
            ->lockForUpdate()
            ->firstOrFail();

        if ($user->role_id === $roleId) {
            return;
        }

        if (
            $role->system_key !== 'administrator'
            && $user->isLastAdministratorWithAccess()
        ) {
            throw ValidationException::withMessages([
                'role_id' => 'Mantenha ao menos um administrador da conta.',
            ]);
        }

        $user->role_id = $role->id;
        $user->is_account_admin = $role->system_key === 'administrator';
        $user->saveOrFail();
    }
}
