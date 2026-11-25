<?php

declare(strict_types=1);

namespace App\Domains\Usuarios\Application\Data;

use Illuminate\Support\Facades\Validator;

final class TwoFactorCodeData
{
    public const ERROR_BAG = 'confirmTwoFactorAuthentication';

    public static function validated(#[\SensitiveParameter] mixed $input): string
    {
        $data = Validator::make(['code' => is_string($input) ? trim($input) : $input], [
            'code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/'],
        ], [
            'code.required' => 'Informe o código do autenticador.',
            'code.string' => 'Informe um código de 6 dígitos.',
            'code.regex' => 'Informe um código de 6 dígitos.',
        ])->validateWithBag(self::ERROR_BAG);

        return $data['code'];
    }
}
