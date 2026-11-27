<?php

declare(strict_types=1);

namespace App\Domains\Midia\Domain\Exceptions;

use Illuminate\Validation\ValidationException;

/** Imagem recusada (tipo, tamanho, dimensões, memória, tempo) ou metadado inválido. Responde 422 no campo indicado. */
final class InvalidMedia extends ValidationException
{
    public static function in(string $field, string $message): self
    {
        return self::withMessages([$field => $message]);
    }

    public static function file(string $message): self
    {
        return self::withMessages(['file' => $message]);
    }
}
