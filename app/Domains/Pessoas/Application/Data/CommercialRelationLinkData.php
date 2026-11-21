<?php

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
