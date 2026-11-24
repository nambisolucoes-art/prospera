<?php

namespace App\Domains\Acessos\UI\Controllers;

use App\Domains\Acessos\Application\Actions\AcceptAccountInvitationAction;
use App\Domains\Acessos\Application\Actions\RevokeAccountInvitationAction;
use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Queries\AccountInvitationsQuery;
use App\Support\ApiPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AccountInvitationController
{
    /** Só a identidade convidada vê a conta do convite; para as demais, o convite é indisponível. */
    public function show(Request $request, AccountInvitationsQuery $invitations, string $token): JsonResponse|View
    {
        $user = $request->user();
        $invitation = $invitations->forToken($token);

        $available = $invitation !== null
            && $invitation->isUsable()
            && $invitation->isFor($user)
            && $invitation->account->active
            && ! $user->is_saas_admin;

        return ApiPage::respond([
            'invitation' => $available ? [
                'account' => $invitation->account->name,
                'role' => $invitation->role?->name,
                'expires_at' => $invitation->expires_at->toIso8601String(),
                'enabled' => (bool) config('prospera.multiple_accounts'),
            ] : null,
            'message' => $available ? null : AcceptAccountInvitationAction::UNAVAILABLE,
        ]);
    }

    public function accept(Request $request, AcceptAccountInvitationAction $accept, string $token): RedirectResponse
    {
        $account = $accept->execute($request->user(), $token);
        ApiPage::toast('Convite aceito. Você já pode trabalhar nesta conta.');

        return redirect('/contas/'.$account->code);
    }

    public function revoke(RevokeAccountInvitationAction $revoke, int $invitationId): RedirectResponse
    {
        $revoke->execute(app(AccountContext::class)->requireId(), $invitationId);
        ApiPage::toast('Convite revogado.');

        return to_route('account.users.index');
    }
}
