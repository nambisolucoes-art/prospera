<?php

declare(strict_types=1);

namespace App\Domains\Usuarios\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Throwable;

final class SendUserInvitationAction
{
    public function execute(User $user): bool
    {
        try {
            return Password::sendResetLink(['email' => $user->email]) === Password::ResetLinkSent;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
