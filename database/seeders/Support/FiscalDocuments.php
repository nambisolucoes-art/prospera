<?php

namespace Database\Seeders\Support;

use InvalidArgumentException;

/**
 * CPF e CNPJ sintéticos com dígitos verificadores corretos, para passarem pela mesma validação da aplicação
 * (App\Support\TaxDocument). Não correspondem a pessoas ou empresas reais conhecidas.
 */
final class FiscalDocuments
{
    /** CNPJ completo a partir da raiz (8 dígitos) e da ordem do estabelecimento (4 dígitos, 0001 na matriz). */
    public static function cnpj(string $root, string $order): string
    {
        $base = $root.$order;

        if (preg_match('/^\d{12}$/', $base) !== 1) {
            throw new InvalidArgumentException('Informe raiz com 8 dígitos e ordem com 4 dígitos.');
        }

        $first = self::cnpjDigit($base);
        $second = self::cnpjDigit($base.$first);

        return $base.$first.$second;
    }

    /** CPF completo a partir dos 9 primeiros dígitos. */
    public static function cpf(string $base): string
    {
        if (preg_match('/^\d{9}$/', $base) !== 1) {
            throw new InvalidArgumentException('Informe os 9 primeiros dígitos do CPF.');
        }

        $first = self::cpfDigit($base);
        $second = self::cpfDigit($base.$first);

        return $base.$first.$second;
    }

    private static function cnpjDigit(string $digits): int
    {
        $weights = strlen($digits) === 12
            ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
            : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $sum = 0;

        foreach (str_split($digits) as $index => $digit) {
            $sum += (int) $digit * $weights[$index];
        }

        $rest = $sum % 11;

        return $rest < 2 ? 0 : 11 - $rest;
    }

    private static function cpfDigit(string $digits): int
    {
        $weight = strlen($digits) + 1;
        $sum = 0;

        foreach (str_split($digits) as $digit) {
            $sum += (int) $digit * $weight;
            $weight--;
        }

        $rest = ($sum * 10) % 11;

        return $rest === 10 ? 0 : $rest;
    }
}
