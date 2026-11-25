<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Actions\Roles;

use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Application\Data\Roles\CreateRoleData;
use App\Domains\Acessos\Domain\DTOs\Roles\CreateRoleDTO;
use App\Domains\Acessos\Domain\Exceptions\RoleChangeNotAllowed;
use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Plataforma\Domain\Models\Client;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Cadastra um cargo da conta. Quem cadastra só concede permissões que possui. */
final readonly class CreateRoleAction
{
    public function __construct(
        private SyncRoleAvailabilityAction $syncAvailability,
        private EffectivePermissions $permissions,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws RoleChangeNotAllowed
     * @throws ValidationException
     */
    public function execute(CreateRoleData $data, int $accountId): Role
    {
        $role = AuditLog::transaction(function () use ($data, $accountId): Role {
            Client::whereKey($accountId)->lockForUpdate()->firstOrFail();
            $this->permissions->forget();

            Gate::authorize('account.roles.create');

            $grantsMissingPermission = Permission::whereIn('id', $data->permissionIds)
                ->whereNotIn('slug', auth()->user()->permissionSlugs())
                ->exists();

            if ($grantsMissingPermission) {
                throw RoleChangeNotAllowed::permissionsNotHeld();
            }

            $roleDTO = new CreateRoleDTO(
                name: $data->name,
                availability: $data->availability->mode,
            );

            $role = Role::register($roleDTO, $accountId);
            $role->saveOrFail();

            $this->syncAvailability->execute($role, $data->availability);
            $role->permissions()->sync($data->permissionIds);
            $role->recordAuditFieldChange('permission_ids', [], $data->permissionIds, 'Permissões');

            return $role;
        });

        $this->permissions->forget();

        return $role;
    }
}
