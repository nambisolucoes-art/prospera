<?php

declare(strict_types=1);

namespace App\Support;

final class PageSize
{
    public static function fromInput(mixed $value): int
    {
        $size = filter_var($value, FILTER_VALIDATE_INT);

        return in_array($size, [10, 25, 50, 100], true) ? $size : 25;
    }
}
