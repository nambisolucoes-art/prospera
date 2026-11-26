<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Domain\Exceptions;

use Illuminate\Validation\ValidationException;

/**
 * Regra de horário violada (turno, período, exceção). É uma exceção de domínio que a interface já entende como erro de
 * campo (422): estende `ValidationException` para que a mensagem chegue ao campo certo sem tratamento extra.
 */
final class InvalidHourException extends ValidationException
{
    public static function in(string $field, string $message): self
    {
        return self::withMessages([$field => $message]);
    }

    /** Repete o erro sob um prefixo, como `value.days.mon.shifts`, quando o valor está dentro de outro objeto. */
    public function prefixed(string $prefix): self
    {
        $errors = [];

        foreach ($this->errors() as $field => $messages) {
            $errors[$prefix.'.'.$field] = $messages[0];
        }

        return self::withMessages($errors);
    }
}
