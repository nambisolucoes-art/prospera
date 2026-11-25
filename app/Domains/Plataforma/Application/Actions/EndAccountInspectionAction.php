<?php

declare(strict_types=1);

namespace App\Domains\Plataforma\Application\Actions;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Plataforma\Application\Queries\AccountInspectionUsersQuery;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Domains\Plataforma\Infrastructure\Session\AccountInspectionSession;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

final readonly class EndAccountInspectionAction
{
    public function __construct(private AccountInspectionUsersQuery $users) {}

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
            ['ending' => ['label' => 'Encerramento', 'before' => null, 'after' => 'Solicitado pelo administrador']],
        );

        $administrator = $this->users->administrator($inspection->administratorId);

        $session->invalidate();
        $session->regenerateToken();

        Log::notice('Inspeção de conta encerrada.', AccountInspectionSession::context($inspection));

        if ($administrator === null) {
            Auth::guard('web')->logoutCurrentDevice();

            return false;
        }

        Auth::guard('web')->login($administrator);

        return true;
    }
}
