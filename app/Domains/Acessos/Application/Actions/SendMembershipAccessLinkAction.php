<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Actions;

use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Usuarios\Application\Actions\SendUserInvitationAction;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class SendMembershipAccessLinkAction
{
    public function __construct(private SendUserInvitationAction $links) {}

    public function execute(AccountMembership $membership, bool $invitation): void
    {
        $node = AccessNode::tenant($membership->account_id);

        if ($invitation) {
            Gate::authorize('account.users.invite', $node);
        } else {
            Gate::authorize('account.users.send-password-reset', $node);
        }

        $identity = $membership->identity()->firstOrFail();

        if ($invitation && ! $identity->password_setup_pending) {
            throw ValidationException::withMessages(['user' => 'O convite só pode ser reenviado se a senha inicial estiver pendente.']);
        }

        $this->links->execute($identity);

        AuditLog::record(
            'account-membership',
            $membership->id,
            $membership->account_id,
            $invitation ? 'invitation-requested' : 'password-reset-requested',
            'Acesso à conta',
        );
    }
}
