<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Data\Profile;

use App\Domains\Institucional\Domain\Enums\ProfileSectionType;
use App\Support\Exceptions\ResourceNotAvailable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Cadastro de uma seção que o dono ainda não tem (não há valor próprio atual).
 * O modo e o formato do valor são validados aqui; as regras que dependem do dono (empresa só personaliza, seção local,
 * seção que não pode ser desativada) são invariantes do Model.
 */
final readonly class CreateProfileSectionData
{
    /** @param array<string,mixed>|null $value */
    public function __construct(
        public ProfileSectionType $section,
        public string $mode,
        public ?array $value,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     *
     * @throws ResourceNotAvailable
     * @throws ValidationException
     */
    public static function fromInput(string $section, array $input): self
    {
        $type = ProfileSectionType::tryFrom($section);

        // Seção desconhecida responde como inexistente.
        if ($type === null) {
            throw new ResourceNotAvailable;
        }

        $values = Validator::validate($input, self::rules(), self::messages());

        if (! $type->isAvailable()) {
            throw ValidationException::withMessages(['section' => $type->unavailableReason()]);
        }

        if ($values['mode'] !== 'override') {
            return new self($type, $values['mode'], null);
        }

        return new self($type, $values['mode'], self::valueOf($type, $input['value'] ?? null));
    }

    /** @return array<string,list<string>> */
    public static function rules(): array
    {
        return [
            'mode' => ['required', 'string', 'in:inherit,override,disabled'],
        ];
    }

    /** @return array<string,string> */
    public static function messages(): array
    {
        return [
            'mode.required' => 'Escolha herdar, personalizar ou desativar.',
            'mode.in' => 'Escolha herdar, personalizar ou desativar.',
        ];
    }

    /**
     * Valor do `override`: obrigatório, objeto, validado pela classe da seção.
     *
     * @return array<string,mixed>
     *
     * @throws ValidationException
     */
    private static function valueOf(ProfileSectionType $type, mixed $raw): array
    {
        if ($raw === null) {
            throw ValidationException::withMessages(['value' => 'Informe o conteúdo da seção.']);
        }

        if (! is_array($raw)) {
            throw ValidationException::withMessages(['value' => 'O conteúdo da seção deve ser um objeto.']);
        }

        $class = $type->valueClass();

        try {
            return $class::fromInput($raw, [])->toArray();
        } catch (ValidationException $failure) {
            $errors = [];

            foreach ($failure->errors() as $key => $messages) {
                $errors[str_starts_with($key, 'value.') ? $key : 'value.'.$key] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }
    }
}
