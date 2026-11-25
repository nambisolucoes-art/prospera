<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Application\Data;

use App\Domains\Pessoas\Domain\Models\Person;
use App\Support\TaxDocument;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class SavePersonDetailsData
{
    /** @param array<string,mixed> $values */
    public function __construct(public array $values) {}

    /** @param array<string,mixed> $input */
    public static function fromInput(array $input, int $accountId, ?Person $existing): self
    {
        $fields = [
            'person_type',
            'document',
            'trade_name',
            'state_registration',
            'municipal_registration',
            'contact_name',
            'whatsapp',
            'website',
            'address',
            'additional_phones',
            'additional_emails',
        ];

        // Campos ausentes no envio mantêm o valor atual da pessoa.
        $values = array_replace(
            $existing?->only($fields) ?? ['person_type' => 'individual'],
            array_intersect_key($input, array_flip($fields)),
        );

        foreach ($values as $key => $value) {
            if (is_string($value)) {
                $values[$key] = (trim($value) === '' ? null : trim($value));
            }
        }

        if (is_string($values['document'] ?? null)) {
            $values['document'] = TaxDocument::normalize($values['document']) ?: null;
        }

        if (is_string($values['whatsapp'] ?? null)) {
            $values['whatsapp'] = preg_replace('/[.\/()\s-]+/', '', $values['whatsapp']);
        }

        if (is_array($values['address'] ?? null)) {
            foreach ($values['address'] as $key => $value) {
                if (is_string($value)) {
                    $values['address'][$key] = (trim($value) === '' ? null : trim($value));
                }
            }

            if (is_string($values['address']['postal_code'] ?? null)) {
                $values['address']['postal_code'] = preg_replace('/[\s-]/', '', $values['address']['postal_code']);
            }

            if (is_string($values['address']['state'] ?? null)) {
                $values['address']['state'] = strtoupper($values['address']['state']);
            }
        }

        foreach (['additional_phones', 'additional_emails'] as $field) {
            if (! is_array($values[$field] ?? null)) {
                continue;
            }

            $normalize = static function (mixed $value) use ($field): mixed {
                if (! is_string($value)) {
                    return $value;
                }

                if ($field === 'additional_phones') {
                    return preg_replace('/[.\/()\s-]+/', '', trim($value));
                }

                return mb_strtolower(trim($value));
            };

            $values[$field] = array_values(array_map($normalize, $values[$field]));
        }

        $company = ($values['person_type'] ?? null) === 'company';
        $documentRule = function (string $attribute, mixed $value, Closure $fail) use ($company): void {
            $document = match (true) {
                ! is_string($value) => null,
                $company => TaxDocument::cnpj($value),
                default => TaxDocument::cpf($value),
            };

            if ($document === null) {
                $fail($company ? 'Informe um CNPJ válido.' : 'Informe um CPF válido.');
            }
        };

        $uniqueDocument = Rule::unique('people', 'document')
            ->where('account_id', $accountId)
            ->ignore($existing?->id);

        $states = explode(' ', 'AC AL AP AM BA CE DF ES GO MA MT MS MG PA PB PR PE PI RJ RN RS RO RR SC SP SE TO');

        $data = Validator::validate($values, [
            'person_type' => ['required', Rule::in(['individual', 'company'])],
            'document' => ['nullable', 'string', $documentRule, $uniqueDocument],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'state_registration' => ['nullable', 'string', 'max:30'],
            'municipal_registration' => ['nullable', 'string', 'max:30'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'additional_phones' => ['sometimes', 'nullable', 'array', 'max:20'],
            'additional_phones.*' => ['nullable', 'string', 'max:30'],
            'additional_emails' => ['sometimes', 'nullable', 'array', 'max:20'],
            'additional_emails.*' => ['nullable', 'string', 'email', 'max:255'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'address' => ['nullable', 'array:postal_code,street,number,complement,neighborhood,city,state'],
            'address.postal_code' => ['nullable', 'regex:/^\d{8}$/'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.number' => ['nullable', 'string', 'max:30'],
            'address.complement' => ['nullable', 'string', 'max:150'],
            'address.neighborhood' => ['nullable', 'string', 'max:150'],
            'address.city' => ['nullable', 'string', 'max:150'],
            'address.state' => ['nullable', Rule::in($states)],
        ], [
            'person_type.required' => 'Selecione o tipo de pessoa.',
            'person_type.in' => 'Selecione Pessoa física ou Pessoa jurídica.',
            'document.unique' => 'Já existe uma pessoa com este documento nesta conta.',
            'document.string' => 'Informe um CPF ou CNPJ válido.',
            'additional_phones.array' => 'Informe uma lista de telefones.',
            'additional_phones.max' => 'Informe até 20 telefones adicionais.',
            'additional_phones.*.string' => 'Informe um telefone válido.',
            'additional_phones.*.max' => 'O telefone deve ter até 30 caracteres.',
            'additional_emails.array' => 'Informe uma lista de e-mails.',
            'additional_emails.max' => 'Informe até 20 e-mails adicionais.',
            'additional_emails.*.email' => 'Informe um e-mail válido.',
            'additional_emails.*.string' => 'Informe um e-mail válido.',
            'additional_emails.*.max' => 'O e-mail deve ter até 255 caracteres.',
            'website.url' => 'Informe um site iniciado por http:// ou https://.',
            'address.postal_code.regex' => 'Informe um CEP com 8 dígitos.',
            'address.state.in' => 'Selecione um estado válido.',
            'address.array' => 'Informe um endereço válido.',
            '*.string' => 'Informe um texto válido.',
            '*.max' => 'O valor informado excede o tamanho permitido.',
        ]);

        if (! $company) {
            $data['trade_name'] = null;
        }

        foreach (['additional_phones', 'additional_emails'] as $field) {
            if (array_key_exists($field, $data)) {
                $filled = array_filter($data[$field] ?? [], static fn ($value): bool => filled($value));
                $data[$field] = array_values($filled);
            }
        }

        return new self($data);
    }
}
