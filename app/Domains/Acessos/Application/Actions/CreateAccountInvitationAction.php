<?php

namespace App\Domains\Acessos\Application\Actions;

use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Acessos\Domain\Models\AccountInvitation;
use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Auditoria\Application\Actions\AuditContext;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Convida uma identidade existente para a conta (E09). A resposta ao operador é sempre a mesma: nenhum
 * resultado revela se o e-mail tem identidade, se já participa da conta ou de outras contas.
 */
final readonly class CreateAccountInvitationAction
{
    public function __construct(
        private SaveRoleAssignmentsAction $assignments,
        private AuditContext $audit,
        private EffectivePermissions $permissions,
    ) {}

    public function execute(Client $account, string $email, int $roleId, AccessNode $node, ?Person $person): void
    {
        if (! config('prospera.multiple_accounts')) {
            return;
        }

        $token = Str::random(64);

        $invitation = AuditLog::transaction(function () use ($account, $email, $roleId, $node, $person, $token) {
            Client::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $this->permissions->forget();
            Gate::authorize('account.users.create', $node);
            $role = $this->assignments->assertAssignable($account->id, $roleId, $node);

            $identity = User::whereRaw('lower(email) = ?', [mb_strtolower($email)])->first();

            // Administradores da plataforma não têm vínculos; identidades sem acesso não são convidadas.
            $invitable = $identity !== null
                && ! $identity->is_saas_admin
                && $identity->access_enabled;

            if (! $invitable) {
                return null;
            }

            $alreadyMember = AccountMembership::where('account_id', $account->id)
                ->where('user_id', $identity->id)
                ->exists();

            if ($alreadyMember) {
                return null;
            }

            if ($person !== null) {
                abort_unless($person->account_id === $account->id, 404);
            }

            // Um convite pendente por e-mail: o novo envio revoga o anterior.
            $pending = AccountInvitation::where('account_id', $account->id)
                ->where('email', mb_strtolower($identity->email))
                ->where('status', 'pending')
                ->lockForUpdate()
                ->get();

            foreach ($pending as $previous) {
                $previous->markRevoked();
                $previous->saveOrFail();
            }

            $invitation = AccountInvitation::issue(
                accountId: $account->id,
                recipient: $identity,
                role: $role,
                node: $node,
                personId: $person?->id,
                invitedBy: $this->audit->actorId ?? auth()->user()?->id,
                token: $token,
                expiresAt: now()->addDays(max(1, (int) config('prospera.invitation_days', 7))),
            );
            $invitation->saveOrFail();

            return $invitation;
        });

        if ($invitation === null) {
            return;
        }

        $link = route('invitations.show', ['token' => $token]);
        $text = 'Você recebeu um convite para acessar a conta '.$account->name.' no Prospera. '
            .'Entre com o seu e-mail e a sua senha atuais e confirme neste link: '.$link.' '
            .'O convite vale até '.$invitation->expires_at->format('d/m/Y H:i').'.';

        try {
            Mail::raw($text, function (Message $message) use ($invitation): void {
                $message->to($invitation->email)->subject('Convite para uma conta do Prospera');
            });

            AuditLog::record(
                'account-invitation',
                $invitation->id,
                $invitation->account_id,
                'invitation-sent',
                'Convite para a conta',
                companyId: $invitation->company_id,
                establishmentId: $invitation->establishment_id,
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
