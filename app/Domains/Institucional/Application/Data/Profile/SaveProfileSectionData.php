<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Data\Profile;

use App\Domains\Institucional\Domain\Enums\ProfileSectionType;
use App\Support\Exceptions\ResourceNotAvailable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Gravação de uma seção: modo e, no `override`, o objeto da seção. Campos ausentes dentro do valor mantêm o valor
 * próprio atual; limpar é enviar o campo vazio de propósito.
 */
final readonly class SaveProfileSectionData
{
    /** @param array<string,mixed>|null $value */
    public function __construct(
        public ProfileSectionType $section,
        public string $mode,
        public ?array $value,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     * @param  array<string,mixed>|null  $currentOwn  valor próprio atual de trabalho, quando o modo atual é `override`
     *
     * @throws ResourceNotAvailable
     * @throws ValidationException
     */
    public static function fromInput(string $section, array $input, bool $isEstablishment, ?array $currentOwn): self
    {
        $type = ProfileSectionType::tryFrom($section);

        // Seção desconhecida responde como inexistente.
        if ($type === null) {
            throw new ResourceNotAvailable;
        }

        $values = Validator::validate($input, ['mode' => ['required', 'string', 'in:inherit,override,disabled']], [
            'mode.required' => 'Escolha herdar, personalizar ou desativar.',
            'mode.in' => 'Escolha herdar, personalizar ou desativar.',
        ]);

        $mode = $values['mode'];

        if (! $type->isAvailable()) {
            throw ValidationException::withMessages(['section' => $type->unavailableReason()]);
        }

        if (! $isEstablishment && $mode !== 'override') {
            throw ValidationException::withMessages(['mode' => 'A empresa define o padrão: só é possível personalizar.']);
        }

        if ($type->isEstablishmentOnly() && ! $isEstablishment) {
            throw ValidationException::withMessages(['section' => 'Esta seção existe só nos estabelecimentos.']);
        }

        if ($mode === 'inherit' && $type->isEstablishmentOnly()) {
            throw ValidationException::withMessages(['mode' => 'Esta seção é local e não herda da empresa.']);
        }

        if ($mode === 'disabled' && ! $type->canDisable()) {
            throw ValidationException::withMessages(['mode' => 'Esta seção não pode ser desativada.']);
        }

        if ($mode !== 'override') {
            return new self($type, $mode, null);
        }

        $raw = $input['value'] ?? null;

        // Sem `value`, o PATCH só muda o modo e mantém o conteúdo próprio atual. Sem conteúdo nenhum, é 422.
        if ($raw === null && $currentOwn === null) {
            throw ValidationException::withMessages(['value' => 'Informe o conteúdo da seção.']);
        }

        if ($raw !== null && ! is_array($raw)) {
            throw ValidationException::withMessages(['value' => 'O conteúdo da seção deve ser um objeto.']);
        }

        $class = $type->valueClass();

        try {
            $value = $class::fromInput($raw ?? [], $currentOwn ?? [])->toArray();
        } catch (ValidationException $failure) {
            $errors = [];

            foreach ($failure->errors() as $key => $messages) {
                $errors[str_starts_with($key, 'value.') ? $key : 'value.'.$key] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }

        return new self($type, $mode, $value);
    }
}
