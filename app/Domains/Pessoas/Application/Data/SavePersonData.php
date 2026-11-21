<?php

namespace App\Domains\Pessoas\Application\Data;

use App\Domains\Pessoas\Domain\Models\Person;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class SavePersonData
{
    /** @param array<string,mixed> $values */
    public function __construct(public array $values) {}

    /** @param array<string,mixed> $input */
    public static function forCommercialReuse(
        array $input,
        Person $person,
        bool $allowIdentificationChange = false,
    ): self {
        if (! $allowIdentificationChange && array_key_exists('person_type', $input)) {
            throw ValidationException::withMessages(['person_type' => 'O tipo de pessoa usado para localizar o cadastro permanece fixo nesta etapa.']);
        }

        if (! $allowIdentificationChange && array_key_exists('document', $input)) {
            throw ValidationException::withMessages([
                'document' => 'O CPF ou CNPJ usado para localizar a pessoa permanece fixo neste cadastro.',
            ]);
        }

        if (is_array($input['address'] ?? null)) {
            $input['address'] = array_replace($person->address ?? [], $input['address']);
        }

        return self::fromInput($input, $person->account_id, $person);
    }

    /** @param array<string,mixed> $input */
    public static function fromInput(array $input, int $accountId, ?Person $existingPerson = null): self
    {
        // Identificação somente leitura não é enviada pelo formulário e mantém o valor atual.
        if (! array_key_exists('name', $input) && $existingPerson !== null) {
            $input['name'] = $existingPerson->name;
        }

        $input['name'] = is_string($input['name'] ?? null) ? trim($input['name']) : null;

        // Telefone ausente no envio mantém o valor atual da pessoa.
        if (! array_key_exists('phone', $input) && $existingPerson !== null) {
            $input['phone'] = $existingPerson->phone;
        }

        if (is_string($input['phone'] ?? null)) {
            $input['phone'] = preg_replace('/[.\/()\s-]+/', '', $input['phone']);
        }

        if (! array_key_exists('email', $input) && $existingPerson !== null) {
            $input['email'] = $existingPerson->email;
        }

        $input['email'] = is_string($input['email'] ?? null) ? mb_strtolower(trim($input['email'])) : null;

        $values = Validator::validate($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ], [
            'name.required' => 'Informe o nome.',
            'name.string' => 'Informe um nome válido.',
            'name.max' => 'O nome deve ter até 255 caracteres.',
            'email.email' => 'Informe um e-mail válido.',
            'email.max' => 'O e-mail deve ter até 255 caracteres.',
            'phone.string' => 'Informe um telefone válido.',
            'phone.max' => 'O telefone deve ter até 30 caracteres.',
        ]);
        $details = SavePersonDetailsData::fromInput($input, $accountId, $existingPerson);

        return new self([...$values, ...$details->values]);
    }
}
