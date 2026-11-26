<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Domain\Exceptions;

use Illuminate\Validation\ValidationException;

/** O fuso não é um identificador IANA. Responde como erro do campo `timezone` (422). */
final class InvalidTimezone extends ValidationException
{
    public static function for(string $timezone): self
    {
        return self::withMessages([
            'timezone' => 'Escolha um fuso da lista, como America/Sao_Paulo ou America/Manaus.',
        ]);
    }
}
