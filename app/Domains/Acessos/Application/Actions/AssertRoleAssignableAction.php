<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Actions;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Support\Exceptions\OperationNotAllowed;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/** Confere que o operador pode atribuir o cargo no nó: disponibilidade e permissões que ele possui. */
final readonly class AssertRoleAssignableAction
{
    public function __construct(
        private AccountContext $accounts,
        private EffectivePermissions $permissions,
    ) {}

    /** @throws AuthorizationException */
    public function execute(int $accountId, int $roleId, ?AccessNode $node = null): Role
    {
        $node ??= AccessNode::tenant($accountId);
        if (! ($this->accounts->requireId() === $accountId && $node->accountId === $accountId)) {
            throw new OperationNotAllowed;
        }

        Gate::authorize('account.users.assign-role', $node);

        $role = Role::where('account_id', $accountId)->findOrFail($roleId);
        if (! $role->isAvailableAt($node)) {
            throw new OperationNotAllowed('O cargo não está disponível neste local.');
        }

        $operator = auth()->user();

        foreach ($role->permissions()->pluck('slug') as $slug) {
            if (! $this->permissions->canDelegate($operator, $slug, $node)) {
                throw new OperationNotAllowed('Você não pode gerenciar um cargo com permissões que não possui neste local.');
            }
        }

        return $role;
    }
}
