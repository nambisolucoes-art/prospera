<?php

declare(strict_types=1);

namespace App\Domains\Plataforma\Application\Actions;

use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Auditoria\Application\Actions\AuditContext;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Plataforma\Application\Data\StartAccountInspectionData;
use App\Domains\Plataforma\Application\Queries\Inspections\ShowInspectableUserQuery;
use App\Domains\Plataforma\Application\Queries\Inspections\ShowInspectionAdministratorQuery;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Domains\Plataforma\Domain\ValueObjects\AccountInspection;
use App\Domains\Plataforma\Infrastructure\Session\AccountInspectionSession;
use App\Models\User;
use App\Support\Exceptions\OperationNotAllowed;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class StartAccountInspectionAction
{
    public function __construct(
        private ShowInspectionAdministratorQuery $showInspectionAdministrator,
        private ShowInspectableUserQuery $showInspectableUser,
    ) {}

    public function execute(
        User $administrator,
        Client $account,
        StartAccountInspectionData $data,
        Session $session,
    ): void {
        if ($this->showInspectionAdministrator->execute($administrator->id) === null) {
            throw new OperationNotAllowed;
        }
        if (AccountInspectionSession::current($session) !== null) {
            throw new OperationNotAllowed;
        }

        $target = $account->active ? $this->showInspectableUser->execute($account, $data->userId) : null;

        if ($target === null) {
            throw ValidationException::withMessages([
                'user_id' => 'Selecione um usuário com acesso a esta conta ativa.',
            ]);
        }

        $membership = AccountMembership::where('account_id', $account->id)
            ->where('user_id', $target->id)
            ->where('status', 'active')
            ->firstOrFail();

        $inspectionDTO = new AccountInspection(
            administratorId: $administrator->id,
            accountId: $account->id,
            userId: $target->id,
            mode: $data->mode,
            membershipId: $membership->id,
            id: (string) Str::uuid(),
        );

        app(AuditContext::class)->identifyInspection($inspectionDTO, $administrator->name, $target->name);
        AuditLog::record(
            'account',
            $account->id,
            $account->id,
            'inspection-started',
            $account->name,
            [
                'mode' => ['label' => 'Modo de inspeção', 'before' => null, 'after' => $data->mode->value],
                'represented' => ['label' => 'Usuário representado', 'before' => null, 'after' => $target->name],
            ],
        );

        $session->invalidate();
        Auth::guard('web')->login($target);
        AccountInspectionSession::start($session, $inspectionDTO);

        Log::notice('Inspeção de conta iniciada.', AccountInspectionSession::context($inspectionDTO));
    }
}
