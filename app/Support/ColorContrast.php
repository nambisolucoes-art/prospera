<?php

declare(strict_types=1);

namespace App\Support;

/** Razão de contraste da WCAG entre duas cores hexadecimais (`#rrggbb`), de 1 a 21. */
final class ColorContrast
{
    public static function ratio(string $first, string $second): float
    {
        $one = self::luminance($first);
        $two = self::luminance($second);

        return (max($one, $two) + 0.05) / (min($one, $two) + 0.05);
    }

    private static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');

        $channels = array_map(
            static function (string $pair): float {
                $value = hexdec($pair) / 255;

                return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            },
            str_split($hex, 2),
        );

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
