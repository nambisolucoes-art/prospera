<?php

declare(strict_types=1);

namespace App\Domains\Plataforma\Application\Data\Accounts;

use App\Domains\Plataforma\Domain\Models\Client;
use App\Support\TaxDocument;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Edição de uma conta pela plataforma. Campos ausentes no envio mantêm o valor atual. */
final readonly class UpdateAccountData
{
    private const STATES = 'AC AL AP AM BA CE DF ES GO MA MT MS MG PA PB PR PE PI RJ RN RS RO RR SC SP SE TO';

    /** @param  array<string,mixed>|null  $details */
    public function __construct(
        public string $name,
        public ?string $email,
        public ?string $plan,
        public ?string $personType,
        public ?string $document,
        public bool $active,
        public ?array $details,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     *
     * @throws ValidationException
     */
    public static function fromInput(#[\SensitiveParameter] array $input, Client $client): self
    {
        $input = self::normalize($input);

        $validated = Validator::validate($input, self::rules($input, $client), self::messages());

        foreach (['additional_phones', 'additional_emails'] as $field) {
            if (isset($validated['details'][$field])) {
                $validated['details'][$field] = array_values(array_filter(
                    $validated['details'][$field],
                    static fn (?string $value): bool => filled($value),
                ));
            }
        }

        // Campos ausentes no envio mantêm o valor atual da conta.
        $value = static fn (string $field): mixed => array_key_exists($field, $validated)
            ? $validated[$field]
            : $client->getAttribute($field);

        return new self(
            name: $validated['name'],
            email: $value('email'),
            plan: $value('plan'),
            personType: $value('person_type'),
            document: $value('document'),
            active: array_key_exists('active', $validated) ? (bool) $validated['active'] : $client->active,
            details: $validated['details'] ?? null,
        );
    }

    /**
     * @param  array<string,mixed>  $input
     * @return array<string,mixed>
     */
    private static function normalize(#[\SensitiveParameter] array $input): array
    {
        if (is_string($input['document'] ?? null)) {
            $input['document'] = TaxDocument::normalize($input['document']);
        }

        foreach (['name', 'email', 'plan'] as $field) {
            if (is_string($input[$field] ?? null)) {
                $input[$field] = trim($input[$field]);
            }
        }

        if (! is_array($input['details'] ?? null)) {
            return $input;
        }

        $normalize = static fn (string $value): string => preg_replace('/[.\/()\s-]+/', '', $value) ?? $value;

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
     * @param  array<string,mixed>  $input
     * @return array<string,mixed>
     */
    public static function rules(array $input, Client $client): array
    {
        // O documento é exigido quando a edição toca nos dados de identificação.
        $documentRequired = Arr::hasAny($input, ['person_type', 'document', 'details'])
            && ($client->document !== null || filled($input['document'] ?? null));

        $company = ($input['person_type'] ?? null) === 'company';

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
                    $document = $company ? TaxDocument::cnpj($value) : TaxDocument::cpf($value);

                    if ($document === null) {
                        $fail('Informe um CPF ou CNPJ válido para o tipo de pessoa selecionado.');
                    }
                },
                Rule::unique(Client::class, 'document')->ignore($client),
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
            'details.address.state' => ['nullable', Rule::in(explode(' ', self::STATES))],
            'details.whatsapp' => ['nullable', 'regex:/^\d{10,11}$/'],
            'details.additional_phones' => ['sometimes', 'array', 'list', 'max:20'],
            'details.additional_phones.*' => ['nullable', 'string', 'regex:/^\d{10,11}$/'],
            'details.additional_emails' => ['sometimes', 'array', 'list', 'max:20'],
            'details.additional_emails.*' => ['nullable', 'string', 'email', 'max:255'],
            'details.phone' => [Rule::requiredIf($documentRequired), 'nullable', 'regex:/^\d{10,11}$/'],
        ];
    }

    /** @return array<string,string|array<string,string>> */
    public static function messages(): array
    {
        return [
            'string' => 'Informe um texto válido.',
            'max' => ['string' => 'Use até :max caracteres.'],
            'array' => 'Revise as informações deste grupo.',
            'list' => 'Informe uma lista válida de contatos.',
            'name.required' => 'Informe o nome ou a razão social.',
            'email.email' => 'Informe um e-mail de contato válido.',
            'details.address.postal_code.digits' => 'Informe um CEP com 8 dígitos.',
            'details.address.state.in' => 'Selecione um estado válido.',
            'details.phone.required' => 'Informe um telefone.',
            'details.phone.regex' => 'Informe um telefone com DDD e 10 ou 11 dígitos.',
            'details.whatsapp.regex' => 'Informe um WhatsApp com DDD e 10 ou 11 dígitos.',
            'details.additional_phones.max' => 'Adicione até 20 telefones adicionais.',
            'details.additional_phones.*.regex' => 'Informe um telefone com DDD e 10 ou 11 dígitos.',
            'details.additional_emails.max' => 'Adicione até 20 e-mails adicionais.',
            'details.additional_emails.*.email' => 'Informe um e-mail válido.',
            'person_type.required' => 'Selecione o tipo de pessoa.',
            'person_type.in' => 'Selecione um tipo de pessoa válido.',
            'document.required' => 'Informe o CPF ou CNPJ.',
            'document.unique' => 'Já existe uma conta com este documento.',
            'active.boolean' => 'Selecione um status válido.',
        ];
    }
}
