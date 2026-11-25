<?php

declare(strict_types=1);

namespace App\Domains\Usuarios\Application\Actions;

use App\Domains\Usuarios\Application\Data\RequestAccessEmailChangeData;
use App\Domains\Usuarios\Application\Queries\UserEmailExistsQuery;
use App\Models\User;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Timebox;
use Throwable;

/** Envia ao novo endereço o link que confirma a troca do e-mail de acesso. */
final readonly class RequestAccessEmailChangeAction
{
    public function __construct(
        private UserEmailExistsQuery $emails,
        private Timebox $timebox,
    ) {}

    public function execute(RequestAccessEmailChangeData $data, User $user): void
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
}
