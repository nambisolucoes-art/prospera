<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Application\Data\CommercialRelations;

use Illuminate\Support\Facades\Validator;

final readonly class CommercialTagsData
{
    /** @param list<int> $ids */
    private function __construct(public array $ids) {}

    public static function fromInput(mixed $input): self
    {
        $values = Validator::validate(['tag_ids' => $input ?? []], [
            'tag_ids' => ['array', 'max:100'],
            'tag_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);

        return new self(array_values(array_map('intval', $values['tag_ids'])));
    }
}
