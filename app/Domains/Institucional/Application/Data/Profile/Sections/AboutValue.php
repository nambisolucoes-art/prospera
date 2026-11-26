<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Data\Profile\Sections;

use App\Domains\Institucional\Domain\Contracts\SectionValue;
use Illuminate\Support\Facades\Validator;

/** Apresentação institucional em texto simples. Tags e HTML são removidos: o conteúdo nunca é executável. */
final readonly class AboutValue implements SectionValue
{
    /**
     * @param  list<string>  $values
     * @param  list<string>  $differentials
     */
    public function __construct(
        public ?string $title,
        public ?string $description,
        public ?string $mission,
        public array $values,
        public array $differentials,
    ) {}

    public static function fromInput(array $input, array $current): static
    {
        $merged = array_replace(self::empty()->toArray(), $current, array_intersect_key($input, self::empty()->toArray()));

        foreach (['title', 'description', 'mission'] as $field) {
            $merged[$field] = self::plain($merged[$field] ?? null);
        }

        foreach (['values', 'differentials'] as $field) {
            $merged[$field] = array_values(array_filter(
                array_map(fn (mixed $item) => self::plain($item), is_array($merged[$field] ?? null) ? $merged[$field] : []),
                fn (?string $item) => $item !== null,
            ));
        }

        $validated = Validator::validate($merged, [
            'title' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'mission' => ['nullable', 'string', 'max:500'],
            'values' => ['array', 'max:8'],
            'values.*' => ['string', 'max:120'],
            'differentials' => ['array', 'max:8'],
            'differentials.*' => ['string', 'max:120'],
        ], [
            'title.max' => 'O título deve ter até 100 caracteres.',
            'description.max' => 'A descrição deve ter até 2000 caracteres.',
            'mission.max' => 'A missão deve ter até 500 caracteres.',
            'values.max' => 'Informe até 8 valores.',
            'values.*.max' => 'Cada valor deve ter até 120 caracteres.',
            'differentials.max' => 'Informe até 8 diferenciais.',
            'differentials.*.max' => 'Cada diferencial deve ter até 120 caracteres.',
        ]);

        return new self(
            $validated['title'] ?? null,
            $validated['description'] ?? null,
            $validated['mission'] ?? null,
            $validated['values'],
            $validated['differentials'],
        );
    }

    public static function empty(): static
    {
        return new self(null, null, null, [], []);
    }

    public static function default(): static
    {
        return self::empty();
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'mission' => $this->mission,
            'values' => $this->values,
            'differentials' => $this->differentials,
        ];
    }

    private static function plain(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = trim(preg_replace('/[ \t]+/', ' ', strip_tags($value)) ?? '');

        return $text === '' ? null : $text;
    }
}
