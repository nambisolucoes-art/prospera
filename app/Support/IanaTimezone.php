<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeZone;

/**
 * Identificadores IANA aceitos como fuso de um estabelecimento. Só entram nomes da base de fusos (`America/Manaus`):
 * siglas ambíguas (`EST`) e deslocamentos fixos (`-03:00`) não representam regras de horário de verão e são recusados.
 */
final class IanaTimezone
{
    public const DEFAULT = 'America/Sao_Paulo';

    public static function isValid(string $identifier): bool
    {
        return in_array($identifier, DateTimeZone::listIdentifiers(DateTimeZone::ALL), true);
    }

    /**
     * Lista para o seletor: os fusos do Brasil primeiro, depois os demais em ordem alfabética.
     *
     * @return list<array{key: string, label: string}>
     */
    public static function options(): array
    {
        $brazil = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, 'BR');
        $others = array_values(array_diff(DateTimeZone::listIdentifiers(DateTimeZone::ALL), $brazil));

        return array_map(
            fn (string $identifier): array => ['key' => $identifier, 'label' => str_replace('_', ' ', $identifier)],
            [...$brazil, ...$others],
        );
    }
}
