<?php

declare(strict_types=1);

namespace App\Domains\Usuarios\UI\Fortify;

use App\Domains\Usuarios\Application\Data\TwoFactorCodeData;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;

final class ConfirmTwoFactorSetup extends ConfirmTwoFactorAuthentication
{
    public function __invoke($user, #[\SensitiveParameter] $code): void
    {
        $code = TwoFactorCodeData::validated($code);

        try {
            parent::__invoke($user, $code);
        } catch (ValidationException) {
            throw ValidationException::withMessages([
                'code' => 'Código inválido ou expirado. Confira o código no autenticador e tente novamente.',
            ])->errorBag(TwoFactorCodeData::ERROR_BAG);
        }
    }
}
