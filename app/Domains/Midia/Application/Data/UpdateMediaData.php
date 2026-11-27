<?php

declare(strict_types=1);

namespace App\Domains\Midia\Application\Data;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Edição dos metadados de uma mídia: substitui o texto alternativo e a licença. Uso, dono e arquivo não mudam. */
final readonly class UpdateMediaData
{
    public function __construct(
        public ?string $altText,
        public ?string $licenseNote,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     *
     * @throws ValidationException
     */
    public static function fromInput(array $input): self
    {
        foreach (['alt_text', 'license_note'] as $field) {
            if (is_string($input[$field] ?? null)) {
                $input[$field] = trim(preg_replace('/\s+/', ' ', strip_tags($input[$field])) ?? '');
            }
        }

        $values = Validator::validate($input, self::rules(), self::messages());

        return new self(
            altText: $values['alt_text'] ?? null,
            licenseNote: $values['license_note'] ?? null,
        );
    }

    /** @return array<string,list<string>> */
    public static function rules(): array
    {
        return [
            'alt_text' => ['nullable', 'string', 'max:300'],
            'license_note' => ['nullable', 'string', 'max:300'],
        ];
    }

    /** @return array<string,string> */
    public static function messages(): array
    {
        return [
            'alt_text.max' => 'O texto alternativo deve ter até 300 caracteres.',
            'license_note.max' => 'A licença ou o crédito deve ter até 300 caracteres.',
        ];
    }
}
