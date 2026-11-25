<?php

declare(strict_types=1);

namespace App\Domains\Plataforma\Domain\DTOs;

use Illuminate\Support\Str;

/**
 * Código público da conta nas URLs /contas/{conta} (E09).
 *
 * Mesmo formato dos códigos organizacionais: letras minúsculas sem acento, números e hífen, de 2 a 40
 * caracteres. Único na plataforma, garantido por índice único no banco, e imutável na v1. O código só
 * identifica a conta pedida; o acesso continua dependendo do vínculo ativo.
 */
final class AccountCode
{
    public const PATTERN = '/^[a-z0-9][a-z0-9-]{1,39}$/';

    public const ROUTE_PATTERN = '[a-z0-9][a-z0-9-]{1,39}';

    // Segmentos usados pela própria aplicação logo depois de /contas e palavras reservadas da DA-28.
    public const RESERVED = [
        'todos',
        'cadastrar',
        'editar',
        'visualizar',
        'selecionar',
        'convites',
        'inspecao',
        'conta',
        'contas',
    ];

    public const MAX_LENGTH = 40;

    public static function isValid(string $code): bool
    {
        return preg_match(self::PATTERN, $code) === 1
            && ! in_array($code, self::RESERVED, true);
    }

    /** Sugestão a partir do nome fantasia ou do nome, sem acentos e com espaço para o sufixo numérico. */
    public static function suggestFrom(string $name): string
    {
        $base = trim(Str::limit(Str::slug($name), self::MAX_LENGTH - 4, ''), '-');

        if (strlen($base) < 2 || in_array($base, self::RESERVED, true)) {
            $base = $base === '' ? 'nova-conta' : $base.'-conta';
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

        do {
            $ending = '-'.$suffix;
            $candidate = substr($base, 0, self::MAX_LENGTH - strlen($ending)).$ending;
            $suffix++;
        } while (in_array($candidate, $taken, true));

        return $candidate;
    }
}
