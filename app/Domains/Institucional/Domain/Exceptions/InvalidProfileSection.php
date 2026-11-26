<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Domain\Exceptions;

use Illuminate\Validation\ValidationException;

/** Regra de uma seção do perfil violada para o dono. Responde como erro do campo indicado (422). */
final class InvalidProfileSection extends ValidationException
{
    public static function in(string $field, string $message): self
    {
        return self::withMessages([$field => $message]);
    }
}
