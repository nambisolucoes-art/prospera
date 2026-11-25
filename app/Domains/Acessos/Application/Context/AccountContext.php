<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Context;

use App\Domains\Plataforma\Application\Queries\AccountMembershipsQuery;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Domains\Plataforma\Domain\ValueObjects\AccountInspection;
use App\Models\User;

/**
 * Conta da requisição (E09). Vem do código em /contas/{conta}, validado pelo vínculo ativo da identidade.
 *
 * Binding scoped: o estado vale só para a requisição atual e nunca é lido da sessão nem da identidade.
 * Fora das rotas da conta, nenhuma conta está ativa.
 */
final class AccountContext
{
    private ?Client $account = null;

    private ?int $userId = null;

    private ?int $membershipId = null;

    public function __construct(private readonly AccountMembershipsQuery $memberships) {}

    /**
     * Código inexistente ou sem vínculo responde 404, sem revelar a conta. O vínculo suspenso e a conta
     * inativa respondem 403 apenas a quem tem vínculo com ela. Na inspeção, só a conta inspecionada abre.
     */
    public function enter(User $user, string $code, ?AccountInspection $inspection = null): Client
    {
        $this->forget();

        abort_if($user->is_saas_admin || ! $user->access_enabled, 404);

        $found = $this->memberships->forCode($user->id, $code);
        abort_if($found === null, 404);

        [$account, $membershipId, $status] = $found;

        if ($inspection !== null) {
            $sameMembership = $inspection->userId === $user->id
                && $inspection->accountId === $account->id
                && ($inspection->membershipId === null || $inspection->membershipId === $membershipId);

            abort_unless($sameMembership, 404);
        }

        abort_if($user->password_setup_pending, 403, 'Defina sua senha pelo link enviado ao seu e-mail.');

        abort_unless($status === 'active', 403, 'Seu acesso a esta conta está suspenso.');
        abort_unless($account->active, 403, 'Esta conta está inativa.');

        $this->account = $account;
        $this->userId = $user->id;
        $this->membershipId = $membershipId;

        return $account;
    }

    public function linkedId(?User $user): ?int
    {
        if ($user === null || $this->userId !== $user->id) {
            return null;
        }

        return $this->account?->id;
    }

    public function membershipId(?User $user): ?int
    {
        return $this->linkedId($user) === null ? null : $this->membershipId;
    }

    public function forget(): void
    {
        $this->account = null;
        $this->userId = null;
        $this->membershipId = null;
    }

    public function requireId(): int
    {
        return $this->requireAccount()->id;
    }

    public function activeAccount(?User $user): ?Client
    {
        return $this->linkedId($user) === null ? null : $this->account;
    }

    public function requireAccount(int $status = 403): Client
    {
        // Durante a inspeção, o guard já contém o usuário representado.
        $account = $this->activeAccount(auth()->user());
        abort_unless($account !== null, $status);

        return $account;
    }

    /** Caminho dentro da conta da requisição, como /contas/grupo-gartan/usuarios. */
    public function path(string $suffix = ''): string
    {
        $code = $this->requireAccount()->code;
        $suffix = ltrim($suffix, '/');

        return '/contas/'.$code.($suffix === '' ? '' : '/'.$suffix);
    }
}
