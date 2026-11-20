<?php

namespace App\Domains\Plataforma\Application\Actions;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Domains\Plataforma\Infrastructure\Session\AccountInspectionSession;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

final class EndAccountInspectionAction
{
    public function execute(User $inspectedUser, Session $session): bool
    {
        $inspection = AccountInspectionSession::current($session);
        abort_unless($inspection !== null && $inspection->userId === $inspectedUser->id, 403);

        $accountName = Client::whereKey($inspection->accountId)->value('name');

        AuditLog::record(
            'account',
            $inspection->accountId,
            $inspection->accountId,
            'inspection-ended',
            $accountName ?? 'Conta #'.$inspection->accountId,
        );

        $administrator = User::query()->find($inspection->administratorId);

        $session->invalidate();
        $session->regenerateToken();

        Log::notice('Inspeção de conta encerrada.', AccountInspectionSession::context($inspection));

        if (
            $administrator === null
            || ! $administrator->can('access-saas')
            || ! $administrator->hasVerifiedEmail()
        ) {
            Auth::guard('web')->logout();

            return false;
        }

        Auth::guard('web')->login($administrator);

        return true;
    }
}
