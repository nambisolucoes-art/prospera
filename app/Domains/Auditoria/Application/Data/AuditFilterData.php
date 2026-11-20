<?php

namespace App\Domains\Auditoria\Application\Data;

use Illuminate\Support\Facades\Validator;

final readonly class AuditFilterData
{
    /** @param array<string,mixed> $values */
    public function __construct(public array $values) {}

    /** @param array<string,mixed> $input */
    public static function fromInput(array $input, bool $platform): self
    {
        $untilRule = 'nullable|date_format:Y-m-d';

        if (! empty($input['from'])) {
            $untilRule .= '|after_or_equal:from';
        }

        $rules = [
            'search' => 'nullable|string|max:255',
            'entity' => 'nullable|string|max:60',
            'event' => 'nullable|string|max:60',
            'actor' => 'nullable|integer|min:1',
            'from' => 'nullable|date_format:Y-m-d',
            'until' => $untilRule,
        ];

        if ($platform) {
            $rules['account'] = 'nullable|integer|min:1';
        } else {
            $rules['account'] = 'prohibited';
        }

        return new self(Validator::make($input, $rules)->validate());
    }
}
