<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Actions;

use App\Domains\Acessos\Application\Queries\AssignmentNodeQuery;
use App\Domains\Acessos\Domain\Models\AccountInvitation;
use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Aceite do convite pela própria identidade (E09). Valida tudo no servidor, sob trava da conta e do convite,
 * e cria o vínculo uma única vez. Qualquer impedimento responde igual, sem dizer qual foi.
 */
final readonly class AcceptAccountInvitationAction
{
    public const UNAVAILABLE = 'Este convite não está disponível. '
        .'Confira se entrou com o e-mail que recebeu o convite.';

    public function __construct(
        private RegisterAccountMembershipAction $registerMembership,
        private AssignmentNodeQuery $nodes,
    ) {}

    public function execute(User $user, string $token): Client
    {
        abort_unless(
            config('prospera.multiple_accounts'),
            403,
            'A entrada em outras contas por convite ainda não está liberada.',
        );

        $hash = AccountInvitation::hashToken($token);
        $candidate = AccountInvitation::where('token_hash', $hash)->first(['id', 'account_id']);
        abort_if($candidate === null, 410, self::UNAVAILABLE);

        return AuditLog::transaction(function () use ($user, $candidate): Client {
            // Mesma ordem de travas da criação do convite: primeiro a conta, depois o convite.
            $account = Client::whereKey($candidate->account_id)->lockForUpdate()->firstOrFail();
            $lockedInvitation = AccountInvitation::whereKey($candidate->id)->lockForUpdate()->firstOrFail();

            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            $usable = $lockedInvitation->isUsable()
                && $lockedInvitation->isFor($lockedUser)
                && $account->active
                && $lockedUser->access_enabled
                && ! $lockedUser->is_saas_admin
                && $lockedUser->hasVerifiedEmail()
                && ! $lockedUser->password_setup_pending;

            abort_unless($usable, 410, self::UNAVAILABLE);

            $alreadyMember = AccountMembership::where('account_id', $account->id)
                ->where('user_id', $user->id)
                ->exists();

            abort_if($alreadyMember, 410, self::UNAVAILABLE);

            try {
                $node = $this->nodes->resolve(
                    $account->id,
                    $lockedInvitation->scope,
                    $lockedInvitation->company_id,
                    $lockedInvitation->establishment_id,
                    granting: true,
                );
            } catch (ValidationException) {
                abort(410, self::UNAVAILABLE);
            }

            $role = Role::where('account_id', $account->id)->find($lockedInvitation->role_id);
            abort_unless($role !== null && $role->isAvailableAt($node), 410, self::UNAVAILABLE);

            $person = null;

            if ($lockedInvitation->person_id !== null) {
                $person = Person::where('account_id', $account->id)
                    ->lockForUpdate()
                    ->find($lockedInvitation->person_id);

                $personInUse = $person === null
                    || AccountMembership::where('account_id', $account->id)->where('person_id', $person->id)->exists();

                abort_if($personInUse, 410, self::UNAVAILABLE);
            }

            $this->registerMembership->execute($lockedUser, $role, $node, $person, $lockedInvitation->invited_by);

            $lockedInvitation->markAccepted($user->id);
            $lockedInvitation->saveOrFail();

            return $account;
        });
    }
}
