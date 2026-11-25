<?php

declare(strict_types=1);

namespace App\Domains\Usuarios\Application\Actions;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Usuarios\Application\Data\ConfirmAccessEmailChangeData;
use App\Domains\Usuarios\Application\Queries\UserEmailExistsQuery;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Troca o e-mail de acesso depois da confirmação e encerra as outras sessões da identidade. */
final readonly class ConfirmAccessEmailChangeAction
{
    public function __construct(
        private UserEmailExistsQuery $emails,
        private EndOtherIdentitySessionsAction $sessions,
    ) {}

    public function execute(ConfirmAccessEmailChangeData $data, User $user, string $sessionId): bool
    {
        try {
            $previous = AuditLog::transaction(function () use ($user, $data, $sessionId): ?string {
                $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

                if (
                    ! $lockedUser->access_enabled
                    || ! hash_equals($lockedUser->emailChangeRevision(), $data->revision)
                    || $this->emails->execute($data->email)
                ) {
                    return null;
                }

                $previous = $lockedUser->email;
                $lockedUser->email = $data->email;
                $lockedUser->email_verified_at = now();
                $this->sessions->execute($lockedUser, $sessionId);
                DB::table('password_reset_tokens')->where('email', $previous)->delete();

                return $previous;
            });
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        if ($previous === null) {
            return false;
        }

        try {
            Mail::raw('O e-mail de acesso da sua identidade no Prospera foi alterado.', function (Message $message) use ($previous): void {
                $message->to($previous)->subject('Seu e-mail de acesso foi alterado');
            });
        } catch (Throwable $exception) {
            report($exception);
        }

        return true;
    }
}
