<?php

namespace App\Domains\Plataforma\Application\Actions;

use App\Domains\Plataforma\Application\Data\StartAccountInspectionData;
use App\Domains\Plataforma\Application\Queries\AccountInspectionUsersQuery;
use App\Domains\Plataforma\Domain\DTOs\AccountInspectionDTO;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Domains\Plataforma\Infrastructure\Session\AccountInspectionSession;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final readonly class StartAccountInspectionAction
{
    public function __construct(private AccountInspectionUsersQuery $users) {}

    public function execute(User $administrator, Client $account, StartAccountInspectionData $data, Session $session): void
    {
        abort_unless($administrator->can('access-saas') && $administrator->hasVerifiedEmail(), 403);
        abort_if(AccountInspectionSession::current($session) !== null, 403);

        $target = $account->active ? $this->users->find($account, $data->userId) : null;
        if ($target === null) {
            throw ValidationException::withMessages(['user_id' => 'Selecione um usuário com acesso a esta conta ativa.']);
        }

        $inspection = new AccountInspectionDTO($administrator->id, $account->id, $target->id, $data->mode);

        $session->invalidate();
        Auth::guard('web')->login($target);
        AccountInspectionSession::start($session, $inspection);

        Log::notice('Inspeção de conta iniciada.', AccountInspectionSession::context($inspection));
    }
}
