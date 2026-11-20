<?php

namespace App\Domains\Organizacao\Domain\DTOs;

use Illuminate\Support\Str;

/**
 * Código de empresa e de estabelecimento usado nas URLs operacionais (DA-28).
 *
 * Letras minúsculas sem acento, números e hífen, de 2 a 40 caracteres, começando por letra ou número.
 * Único por conta e imutável na v1.
 */
final class OrganizationCode
{
    public const PATTERN = '/^[a-z0-9][a-z0-9-]{1,39}$/';

    public const RESERVED = ['todos', 'cadastrar', 'editar', 'visualizar'];

    public const MAX_LENGTH = 40;

    public static function isValid(string $code): bool
    {
        return preg_match(self::PATTERN, $code) === 1
            && ! in_array($code, self::RESERVED, true);
    }

    /**
     * Base da sugestão: o nome sem acentos, com um sufixo que mantém o resultado válido e livre das reservadas.
     */
    public static function suggestFrom(string $name): string
    {
        $base = trim(Str::limit(Str::slug($name), self::MAX_LENGTH - 3, ''), '-');

        if (strlen($base) < 2 || in_array($base, self::RESERVED, true)) {
            $base = trim($base.'-un', '-');
        }

        return $base;
    }

    /** @param array<int, mixed> $taken */
    public static function firstAvailable(string $base, array $taken): string
    {
        if (! in_array($base, $taken, true)) {
            return $base;
        }

        $suffix = 2;

        while (in_array($base.'-'.$suffix, $taken, true)) {
            $suffix++;
        }

        return $base.'-'.$suffix;
    }
}
