<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Data\Profile\Sections;

use App\Domains\Institucional\Domain\Contracts\SectionValue;
use Illuminate\Support\Facades\Validator;

/** SEO padrão: título e descrição em texto simples. A imagem de compartilhamento chega com a mídia (S08). */
final readonly class SeoDefaultsValue implements SectionValue
{
    public function __construct(
        public ?string $title,
        public ?string $description,
    ) {}

    public static function fromInput(array $input, array $current): static
    {
        $merged = array_replace(self::empty()->toArray(), $current, array_intersect_key($input, self::empty()->toArray()));

        foreach (['title', 'description'] as $field) {
            $text = is_string($merged[$field] ?? null) ? trim(strip_tags($merged[$field])) : '';
            $merged[$field] = $text === '' ? null : $text;
        }

        $validated = Validator::validate($merged, [
            'title' => ['nullable', 'string', 'max:70'],
            'description' => ['nullable', 'string', 'max:160'],
        ], [
            'title.max' => 'O título deve ter até 70 caracteres.',
            'description.max' => 'A descrição deve ter até 160 caracteres.',
        ]);

        return new self($validated['title'] ?? null, $validated['description'] ?? null);
    }

    public static function empty(): static
    {
        return new self(null, null);
    }

    public static function default(): static
    {
        return self::empty();
    }

    public function toArray(): array
    {
        return ['title' => $this->title, 'description' => $this->description];
    }
}
