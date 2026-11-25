<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Application\Data;

use App\Support\TaxDocument;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CommercialRelationLinkData
{
    /** @return array{person_type: 'individual'|'company', document: string} */
    public static function discovery(mixed $input, mixed $personType): array
    {
        if ($personType !== 'individual' && $personType !== 'company') {
            throw ValidationException::withMessages(['person_type' => 'Selecione o tipo de pessoa.']);
        }

        $document = is_string($input) ? $input : null;
        $valid = $personType === 'individual' ? TaxDocument::cpf($document) : TaxDocument::cnpj($document);

        if ($valid === null) {
            $message = $personType === 'individual' ? 'Informe um CPF válido.' : 'Informe um CNPJ válido.';

            throw ValidationException::withMessages(['documento' => $message]);
        }

        return ['person_type' => $personType, 'document' => $valid->value()];
    }

    /** @param array<string,mixed> $input */
    public static function optionalStatus(array $input): ?bool
    {
        $values = Validator::validate($input, [
            'active' => ['sometimes', 'required', 'boolean'],
        ], [
            'active.required' => 'Selecione a situação.',
            'active.boolean' => 'Selecione uma situação válida.',
        ]);

        return array_key_exists('active', $values) ? (bool) $values['active'] : null;
    }

    /**
     * @param  array<string,mixed>  $input
     * @return array{add: list<string>, remove: list<string>}
     */
    public static function companies(array $input, string $origin): array
    {
        $values = Validator::validate($input, [
            'add_companies' => ['sometimes', 'array', 'list'],
            'add_companies.*' => ['required', 'string', 'distinct', 'max:40', 'regex:/^[a-z0-9][a-z0-9-]+$/D'],
            'remove_companies' => ['sometimes', 'array', 'list'],
            'remove_companies.*' => ['required', 'string', 'distinct', 'max:40', 'regex:/^[a-z0-9][a-z0-9-]+$/D'],
        ]);

        $add = $values['add_companies'] ?? [];
        $remove = $values['remove_companies'] ?? [];

        if (in_array($origin, [...$add, ...$remove], true) || array_intersect($add, $remove) !== []) {
            throw ValidationException::withMessages(['add_companies' => 'Revise a seleção de empresas.']);
        }

        return ['add' => $add, 'remove' => $remove];
    }

    public static function personId(mixed $input): int
    {
        $message = 'Busque a pessoa pelo CPF ou CNPJ antes de salvar.';
        $values = Validator::validate(['person_id' => $input], [
            'person_id' => ['required', 'integer', 'min:1'],
        ], [
            'person_id.required' => $message,
            'person_id.integer' => $message,
            'person_id.min' => $message,
        ]);

        return (int) $values['person_id'];
    }
}
