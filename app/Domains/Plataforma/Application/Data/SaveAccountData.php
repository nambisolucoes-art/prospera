<?php

namespace App\Domains\Plataforma\Application\Data;

use App\Domains\Plataforma\Domain\Models\Client;
use App\Domains\Usuarios\Application\Data\CreateUserData;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class SaveAccountData
{
    /** @param array{email?: ?string, plan?: ?string, person_type?: ?string, document?: ?string, active?: bool|int|string, details?: array<string, mixed>} $attributes */
    private function __construct(public string $name, public array $attributes, public ?CreateUserData $administrator) {}

    /** @param array<string, mixed> $input */
    public static function fromInput(#[\SensitiveParameter] array $input, ?Client $client = null, bool $profile = false): self
    {
        $input = self::normalize($input, $client);
        $validated = Validator::validate($input, self::rules($input, $client, $profile), self::messages());
        $administrator = isset($validated['administrator']) ? CreateUserData::fromValidated($validated['administrator']) : null;
        $name = $validated['name'];
        unset($validated['administrator'], $validated['name']);

        foreach (['additional_phones', 'additional_emails'] as $field) {
            if (isset($validated['details'][$field])) {
                $validated['details'][$field] = array_values(array_filter(
                    $validated['details'][$field],
                    static fn (?string $value): bool => filled($value),
                ));
            }
        }

        return new self($name, $validated, $administrator);
    }

    /** @param array<string, mixed> $input */
    public static function fromTenantInput(array $input, Client $account): self
    {
        return self::fromInput(Arr::only($input, ['name', 'email', 'details']), $account, profile: true);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private static function normalize(#[\SensitiveParameter] array $input, ?Client $client): array
    {
        $normalize = static fn (string $value): string => preg_replace('/[.\/()\s-]+/', '', $value) ?? $value;
        if (is_string($input['document'] ?? null)) {
            $input['document'] = $normalize($input['document']);
        }

        if ($client !== null) {
            unset($input['administrator']);
        } elseif (is_array($input['administrator'] ?? null)) {
            $input['administrator'] = CreateUserData::normalize($input['administrator']);
        }

        foreach (['name', 'email', 'plan'] as $field) {
            if (is_string($input[$field] ?? null)) {
                $input[$field] = trim($input[$field]);
            }
        }

        if (! is_array($input['details'] ?? null)) {
            return $input;
        }

        if (is_string($input['details']['address']['postal_code'] ?? null)) {
            $input['details']['address']['postal_code'] = $normalize($input['details']['address']['postal_code']);
        }

        foreach (['phone', 'whatsapp'] as $field) {
            if (is_string($input['details'][$field] ?? null)) {
                $input['details'][$field] = $normalize($input['details'][$field]);
            }
        }

        foreach (['additional_phones' => $normalize, 'additional_emails' => 'trim'] as $field => $sanitize) {
            if (is_array($input['details'][$field] ?? null)) {
                $input['details'][$field] = array_map(
                    static fn (mixed $value): mixed => is_string($value) ? $sanitize($value) : $value,
                    $input['details'][$field],
                );
            }
        }

        return $input;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private static function rules(#[\SensitiveParameter] array $input, ?Client $client, bool $profile): array
    {
        $documentRequired = ! $profile && ($client === null
            || (Arr::hasAny($input, ['person_type', 'document', 'details']) && ($client->getAttribute('document') !== null || filled($input['document'] ?? null))));
        $company = ($profile ? $client?->getAttribute('person_type') : ($input['person_type'] ?? null)) === 'company';
        $uniqueDocument = Rule::unique(Client::class, 'document');
        if ($client !== null) {
            $uniqueDocument->ignore($client);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'plan' => ['nullable', 'string', 'max:100'],
            'person_type' => [Rule::requiredIf($documentRequired), 'nullable', Rule::in(['company', 'individual'])],
            'document' => [
                'bail',
                Rule::requiredIf($documentRequired),
                'nullable',
                'string',
                static function (string $attribute, string $value, Closure $fail) use ($company): void {
                    if (! self::validDocument($value, $company)) {
                        $fail('Informe um CPF ou CNPJ válido para o tipo de pessoa selecionado.');
                    }
                },
                $uniqueDocument,
            ],
            'active' => ['sometimes', 'boolean'],
            'details' => ['sometimes', 'array:trade_name,address,phone,whatsapp,additional_phones,additional_emails'],
            'details.trade_name' => [Rule::excludeIf(! $company), 'nullable', 'string', 'max:255'],
            'details.address' => ['sometimes', 'array:postal_code,street,number,complement,neighborhood,city,state'],
            'details.address.postal_code' => ['nullable', 'digits:8'],
            'details.address.street' => ['nullable', 'string', 'max:255'],
            'details.address.number' => ['nullable', 'string', 'max:10'],
            'details.address.complement' => ['nullable', 'string', 'max:255'],
            'details.address.neighborhood' => ['nullable', 'string', 'max:255'],
            'details.address.city' => ['nullable', 'string', 'max:255'],
            'details.address.state' => ['nullable', Rule::in(explode(' ', 'AC AL AP AM BA CE DF ES GO MA MT MS MG PA PB PR PE PI RJ RN RS RO RR SC SP SE TO'))],
            'details.phone' => [Rule::requiredIf($documentRequired), 'nullable', 'regex:/^\d{10,11}$/'],
            'details.whatsapp' => ['nullable', 'regex:/^\d{10,11}$/'],
            'details.additional_phones' => ['sometimes', 'array', 'list', 'max:20'],
            'details.additional_phones.*' => ['nullable', 'string', 'regex:/^\d{10,11}$/'],
            'details.additional_emails' => ['sometimes', 'array', 'list', 'max:20'],
            'details.additional_emails.*' => ['nullable', 'string', 'email', 'max:255'],
            'administrator' => [Rule::requiredIf($client === null), 'array:name,email,mode,password,password_confirmation'],
            ...($client !== null ? [] : CreateUserData::rules('administrator.')),
        ];
    }

    /** @return array<string, string|array<string, string>> */
    private static function messages(): array
    {
        return [
            ...CreateUserData::messages('administrator.'),
            'string' => 'Informe um texto válido.',
            'max' => ['string' => 'Use até :max caracteres.'],
            'array' => 'Revise as informações deste grupo.',
            'list' => 'Informe uma lista válida de contatos.',
            'name.required' => 'Informe o nome ou a razão social.',
            'email.email' => 'Informe um e-mail de contato válido.',
            'person_type.required' => 'Selecione o tipo de pessoa.',
            'person_type.in' => 'Selecione um tipo de pessoa válido.',
            'document.required' => 'Informe o CPF ou CNPJ.',
            'document.unique' => 'Já existe uma conta com este documento.',
            'active.boolean' => 'Selecione um status válido.',
            'details.address.postal_code.digits' => 'Informe um CEP com 8 dígitos.',
            'details.address.state.in' => 'Selecione um estado válido.',
            'details.phone.required' => 'Informe um telefone.',
            'details.phone.regex' => 'Informe um telefone com DDD e 10 ou 11 dígitos.',
            'details.whatsapp.regex' => 'Informe um WhatsApp com DDD e 10 ou 11 dígitos.',
            'details.additional_phones.max' => 'Adicione até 20 telefones adicionais.',
            'details.additional_phones.*.regex' => 'Informe um telefone com DDD e 10 ou 11 dígitos.',
            'details.additional_emails.max' => 'Adicione até 20 e-mails adicionais.',
            'details.additional_emails.*.email' => 'Informe um e-mail válido.',
            'administrator.required' => 'Preencha os dados do administrador da conta.',
        ];
    }

    private static function validDocument(string $value, bool $company): bool
    {
        if (! ctype_digit($value) || strlen($value) !== ($company ? 14 : 11) || count(array_unique(str_split($value))) === 1) {
            return false;
        }

        $base = substr($value, 0, -2);
        for ($digit = 0; $digit < 2; $digit++) {
            $sum = 0;
            foreach (str_split(strrev($base)) as $index => $number) {
                $weight = $company ? 2 + $index % 8 : 2 + $index;
                $sum += (int) $number * $weight;
            }
            $remainder = $sum % 11;
            $base .= (string) ($remainder < 2 ? 0 : 11 - $remainder);
        }

        return $base === $value;
    }
}
