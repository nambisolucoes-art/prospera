<?php

declare(strict_types=1);

namespace App\Domains\Usuarios\UI\Fortify;

use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse;

final class NeutralFailedPasswordResetResponse implements FailedPasswordResetResponse
{
    public function toResponse($request): RedirectResponse
    {
        $message = 'O link de redefinição é inválido ou expirou.';

        if ($request->wantsJson()) {
            throw ValidationException::withMessages(['email' => $message]);
        }

        return back()->withInput($request->only('email'))->withErrors(['email' => $message]);
    }
}
