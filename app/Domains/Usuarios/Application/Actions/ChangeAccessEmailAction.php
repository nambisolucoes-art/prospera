<?php

declare(strict_types=1);

namespace App\Domains\Usuarios\Application\Actions;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Usuarios\Application\Data\ChangeAccessEmailData;
use App\Domains\Usuarios\Application\Queries\UserEmailExistsQuery;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Timebox;
use Throwable;

final readonly class ChangeAccessEmailAction
{
    public function __construct(
        private UserEmailExistsQuery $emails,
        private EndOtherIdentitySessionsAction $sessions,
        private Timebox $timebox,
    ) {}

    public function request(User $user, ChangeAccessEmailData $data): void
    {
        $this->timebox->call(function () use ($user, $data): void {
            if ($data->email === $user->email || $this->emails->execute($data->email)) {
                return;
            }

            $link = URL::temporarySignedRoute('profile.email.confirm', now()->addMinutes(30), [
                'identity' => $user->id,
                'email' => $data->email,
                'revision' => $user->emailChangeRevision(),
            ]);

            try {
                Mail::raw('Confirme o novo e-mail de acesso ao Prospera neste link: '.$link, function (Message $message) use ($data): void {
                    $message->to($data->email)->subject('Confirme seu novo e-mail de acesso');
                });
            } catch (Throwable $exception) {
                report($exception);
            }
        }, 200_000);
    }

    public function confirm(User $user, ChangeAccessEmailData $data, string $revision, string $sessionId): bool
    {
        try {
            $previous = AuditLog::transaction(function () use ($user, $data, $revision, $sessionId): ?string {
                $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

                if (
                    ! $lockedUser->access_enabled
                    || ! hash_equals($lockedUser->emailChangeRevision(), $revision)
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
