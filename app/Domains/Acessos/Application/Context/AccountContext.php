<?php

namespace App\Domains\Acessos\Application\Context;

use App\Domains\Plataforma\Application\Queries\AccountDirectoryQuery;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Domains\Plataforma\Infrastructure\Session\AccountInspectionSession;
use App\Models\User;

final class AccountContext
{
    /** @var array<string, Client|null> */
    private array $accounts = [];

    public function __construct(private readonly AccountDirectoryQuery $directory) {}

    // O vínculo também identifica eventos de autenticação de contas inativas.
    public function linkedId(?User $user): ?int
    {
        if ($user === null) {
            return null;
        }

        $inspection = request()->hasSession() ? AccountInspectionSession::current(request()->session()) : null;

        if ($inspection?->userId === $user->id) {
            return $inspection->accountId;
        }

        return $user->account_id;
    }

    public function forget(): void
    {
        $this->accounts = [];
    }

    public function requireId(): int
    {
        return $this->requireAccount()->id;
    }

    public function activeAccount(?User $user): ?Client
    {
        $id = $this->linkedId($user);

        if ($id === null || $user?->is_saas_admin || ! $user?->access_enabled) {
            return null;
        }

        $key = $user->id.':'.$id;

        if (! array_key_exists($key, $this->accounts)) {
            $this->accounts[$key] = $this->directory->activeAccount($id, $user->id);
        }

        return $this->accounts[$key];
    }

    public function requireAccount(int $status = 403): Client
    {
        // Durante a inspeção, o guard já contém o usuário representado.
        $account = $this->activeAccount(auth()->user());
        abort_unless($account !== null, $status);

        return $account;
    }
}
