<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Data\Profile\Sections;

use App\Domains\Institucional\Domain\Contracts\SectionValue;
use App\Support\ColorContrast;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Paleta institucional: cinco cores validadas por formato e por contraste, sem cor livre fora desse conjunto. */
final readonly class PaletteValue implements SectionValue
{
    public const TEXT_CONTRAST = 4.5;

    public const BRAND_CONTRAST = 3.0;

    public function __construct(
        public string $primary,
        public string $secondary,
        public string $accent,
        public string $background,
        public string $text,
    ) {}

    public static function fromInput(array $input, array $current): static
    {
        $merged = array_replace(self::default()->toArray(), $current, array_intersect_key($input, self::default()->toArray()));

        $values = Validator::validate($merged, self::rules(), self::messages());
        $palette = new self(...array_map('strtolower', $values));

        $errors = [];

        if (ColorContrast::ratio($palette->text, $palette->background) < self::TEXT_CONTRAST) {
            $errors['text'] = 'O contraste entre o texto e o fundo deve ser de pelo menos 4,5 para 1.';
        }

        if (ColorContrast::ratio($palette->primary, $palette->background) < self::BRAND_CONTRAST) {
            $errors['primary'] = 'O contraste entre a cor principal e o fundo deve ser de pelo menos 3 para 1.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(array_combine(
                array_map(fn (string $key) => 'value.'.$key, array_keys($errors)),
                array_values($errors),
            ));
        }

        return $palette;
    }

    public static function empty(): static
    {
        return self::default();
    }

    public static function default(): static
    {
        return new self('#0b2a4a', '#4d7c0f', '#1d4ed8', '#ffffff', '#1f2937');
    }

    public function toArray(): array
    {
        return [
            'primary' => $this->primary,
            'secondary' => $this->secondary,
            'accent' => $this->accent,
            'background' => $this->background,
            'text' => $this->text,
        ];
    }

    /** @return array<string,list<string>> */
    public static function rules(): array
    {
        $color = ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];

        return ['primary' => $color, 'secondary' => $color, 'accent' => $color, 'background' => $color, 'text' => $color];
    }

    /** @return array<string,string> */
    public static function messages(): array
    {
        $message = 'Informe uma cor no formato #rrggbb.';

        return [
            'primary.regex' => $message, 'secondary.regex' => $message, 'accent.regex' => $message,
            'background.regex' => $message, 'text.regex' => $message,
            'primary.required' => $message, 'secondary.required' => $message, 'accent.required' => $message,
            'background.required' => $message, 'text.required' => $message,
        ];
    }
}
