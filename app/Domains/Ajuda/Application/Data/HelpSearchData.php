<?php

declare(strict_types=1);

namespace App\Domains\Ajuda\Application\Data;

use Illuminate\Support\Facades\Validator;

final class HelpSearchData
{
    /** @param array<string,mixed> $input */
    public static function fromInput(array $input): string
    {
        return trim(Validator::make($input, ['search' => 'nullable|string|max:255'])->validate()['search'] ?? '');
    }
}
