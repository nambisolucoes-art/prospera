<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Data\Profile\Sections;

use App\Domains\Institucional\Domain\Contracts\SectionValue;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Tipografia: duas fontes de um catálogo fechado. Nenhuma URL de fonte nem CSS é aceito. */
final readonly class TypographyValue implements SectionValue
{
    public function __construct(
        public string $heading,
        public string $body,
    ) {}

    public static function fromInput(array $input, array $current): static
    {
        $merged = array_replace(self::default()->toArray(), $current, array_intersect_key($input, self::default()->toArray()));
        $fonts = array_keys(config('institutional.fonts'));

        $values = Validator::validate($merged, [
            'heading' => ['required', 'string', Rule::in($fonts)],
            'body' => ['required', 'string', Rule::in($fonts)],
        ], [
            'heading.in' => 'Escolha uma fonte da lista.',
            'body.in' => 'Escolha uma fonte da lista.',
            'heading.required' => 'Escolha uma fonte da lista.',
            'body.required' => 'Escolha uma fonte da lista.',
        ]);

        return new self($values['heading'], $values['body']);
    }

    public static function empty(): static
    {
        return self::default();
    }

    public static function default(): static
    {
        return new self('noto-sans', 'source-sans-pro');
    }

    public function toArray(): array
    {
        return ['heading' => $this->heading, 'body' => $this->body];
    }
}
