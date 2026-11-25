<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Actions;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Domain\Models\AccountInvitation;
use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Support\Exceptions\OperationNotAllowed;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class RevokeAccountInvitationAction
{
    public function __construct(
        private AccountContext $accounts,
        private EffectivePermissions $permissions,
    ) {}

    public function execute(int $accountId, int $invitationId): void
    {
        if ($this->accounts->requireId() !== $accountId) {
            throw new OperationNotAllowed;
        }
        Gate::authorize('account.users.revoke-invitation', AccessNode::tenant($accountId));

        AuditLog::transaction(function () use ($accountId, $invitationId): void {
            Client::whereKey($accountId)->lockForUpdate()->firstOrFail();
            $this->permissions->forget();
            Gate::authorize('account.users.revoke-invitation', AccessNode::tenant($accountId));

            // Convite de outra conta responde 404.
            $lockedInvitation = AccountInvitation::where('account_id', $accountId)
                ->lockForUpdate()
                ->findOrFail($invitationId);

            if ($lockedInvitation->status !== 'pending') {
                throw ValidationException::withMessages(['invitation' => 'Este convite não está mais pendente.']);
            }

            $lockedInvitation->markRevoked();
            $lockedInvitation->saveOrFail();
        });
    }
}
