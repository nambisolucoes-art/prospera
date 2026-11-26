<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Data\Profile;

use App\Domains\Institucional\Domain\Enums\ProfileSectionType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Publicação de seções editoriais, com a confirmação dos números do resumo de impacto. */
final readonly class PublishProfileSectionsData
{
    /** @param list<ProfileSectionType> $sections */
    public function __construct(
        public array $sections,
        public int $inheriting,
        public int $overriding,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     *
     * @throws ValidationException
     */
    public static function fromInput(array $input): self
    {
        $values = Validator::validate($input, [
            'sections' => ['required', 'array', 'min:1', 'max:20'],
            'sections.*' => ['required', 'string', 'distinct', Rule::in(ProfileSectionType::values())],
            'inheriting' => ['required', 'integer', 'min:0'],
            'overriding' => ['required', 'integer', 'min:0'],
        ], [
            'sections.required' => 'Escolha ao menos uma seção para publicar.',
            'sections.min' => 'Escolha ao menos uma seção para publicar.',
            'sections.*.in' => 'Escolha seções válidas.',
            'inheriting.required' => 'Confirme o resumo de impacto.',
            'overriding.required' => 'Confirme o resumo de impacto.',
        ]);

        $sections = array_map(fn (string $value) => ProfileSectionType::from($value), $values['sections']);

        foreach ($sections as $section) {
            if ($section->isOperational()) {
                throw ValidationException::withMessages([
                    'sections' => $section->label().' é operacional e vale ao salvar. Não há o que publicar.',
                ]);
            }

            if (! $section->isAvailable()) {
                throw ValidationException::withMessages(['sections' => $section->unavailableReason()]);
            }
        }

        return new self(array_values($sections), (int) $values['inheriting'], (int) $values['overriding']);
    }
}
