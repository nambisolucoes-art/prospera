<?php

namespace App\Domains\Acessos\Application\Context;

use App\Domains\Plataforma\Application\Queries\AccountDirectoryQuery;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;

final class AccountContext
{
    /** @var array<int, Client|null> */
    private array $accounts = [];

    public function __construct(private readonly AccountDirectoryQuery $directory) {}

    // O vínculo também identifica eventos de autenticação de contas inativas.
    public function linkedId(?User $user): ?int
    {
        return $user?->account_id;
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

        if (! array_key_exists($id, $this->accounts)) {
            $this->accounts[$id] = $this->directory->activeAccount($id);
        }

        return $this->accounts[$id];
    }

    public function requireAccount(int $status = 403): Client
    {
        // Durante a inspeção, o guard já contém o usuário representado.
        $account = $this->activeAccount(auth()->user());
        abort_unless($account !== null, $status);

        return $account;
    }
}
