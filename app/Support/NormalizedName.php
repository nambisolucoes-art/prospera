<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Nome de cadastro sem espaços repetidos e a chave de comparação sem acentos nem caixa. */
final class NormalizedName
{
    public static function display(string $name): string
    {
        return Str::squish($name);
    }

    public static function key(string $name): string
    {
        return Str::lower(Str::ascii(self::display($name)));
    }
}
