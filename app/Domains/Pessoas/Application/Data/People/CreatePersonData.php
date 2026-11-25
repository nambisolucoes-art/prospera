<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Application\Data\People;

use App\Support\ContactInput;
use App\Support\TaxDocument;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Cadastro de uma pessoa da conta. */
final readonly class CreatePersonData
{
    private const DETAIL_FIELDS = [
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

    private const STATES = 'AC AL AP AM BA CE DF ES GO MA MT MS MG PA PB PR PE PI RJ RN RS RO RR SC SP SE TO';

    /**
     * @param  array<string,string|null>|null  $address
     * @param  array<int,string>  $additionalPhones
     * @param  array<int,string>  $additionalEmails
     */
    public function __construct(
        public string $name,
        public ?string $email,
        public ?string $phone,
        public string $personType,
        public ?string $document,
        public ?string $tradeName,
        public ?string $stateRegistration,
        public ?string $municipalRegistration,
        public ?string $contactName,
        public ?string $whatsapp,
        public ?string $website,
        public ?array $address,
        public array $additionalPhones,
        public array $additionalEmails,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     *
     * @throws ValidationException
     */
    public static function fromInput(array $input, int $accountId): self
    {
        $input = self::normalize(array_replace(['person_type' => 'individual'], $input));

        $validated = Validator::validate(
            $input,
            self::rules($accountId, null, $input['person_type']),
            self::messages(),
        );

        $company = $validated['person_type'] === 'company';

        return new self(
            name: $validated['name'],
            email: $validated['email'] ?? null,
            phone: $validated['phone'] ?? null,
            personType: $validated['person_type'],
            document: $validated['document'] ?? null,
            tradeName: $company ? ($validated['trade_name'] ?? null) : null,
            stateRegistration: $validated['state_registration'] ?? null,
            municipalRegistration: $validated['municipal_registration'] ?? null,
            contactName: $validated['contact_name'] ?? null,
            whatsapp: $validated['whatsapp'] ?? null,
            website: $validated['website'] ?? null,
            address: $validated['address'] ?? null,
            additionalPhones: ContactInput::filled($validated['additional_phones'] ?? null),
            additionalEmails: ContactInput::filled($validated['additional_emails'] ?? null),
        );
    }

    /** @return array<string,list<mixed>> */
    public static function rules(int $accountId, ?int $ignoreId, mixed $personType): array
    {
        $company = $personType === 'company';

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

        $uniqueDocument = Rule::unique('people', 'document')->where('account_id', $accountId);

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
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
            'address.state' => ['nullable', Rule::in(explode(' ', self::STATES))],
        ];
    }

    /** @return array<string,string> */
    public static function messages(): array
    {
        return [
            'name.required' => 'Informe o nome.',
            'name.string' => 'Informe um nome válido.',
            'name.max' => 'O nome deve ter até 255 caracteres.',
            'email.email' => 'Informe um e-mail válido.',
            'email.max' => 'O e-mail deve ter até 255 caracteres.',
            'phone.string' => 'Informe um telefone válido.',
            'phone.max' => 'O telefone deve ter até 30 caracteres.',
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
        ];
    }

    /**
     * @param  array<string,mixed>  $input
     * @return array<string,mixed>
     */
    private static function normalize(array $input): array
    {
        $input['name'] = is_string($input['name'] ?? null) ? trim($input['name']) : null;

        if (is_string($input['phone'] ?? null)) {
            $input['phone'] = preg_replace('/[.\/()\s-]+/', '', $input['phone']);
        }

        $input['email'] = is_string($input['email'] ?? null) ? mb_strtolower(trim($input['email'])) : null;

        foreach (self::DETAIL_FIELDS as $field) {
            if (is_string($input[$field] ?? null)) {
                $input[$field] = trim($input[$field]) === '' ? null : trim($input[$field]);
            }
        }

        if (is_string($input['document'] ?? null)) {
            $input['document'] = TaxDocument::normalize($input['document']) ?: null;
        }

        if (is_string($input['whatsapp'] ?? null)) {
            $input['whatsapp'] = preg_replace('/[.\/()\s-]+/', '', $input['whatsapp']);
        }

        if (is_array($input['address'] ?? null)) {
            foreach ($input['address'] as $key => $value) {
                if (is_string($value)) {
                    $input['address'][$key] = trim($value) === '' ? null : trim($value);
                }
            }

            if (is_string($input['address']['postal_code'] ?? null)) {
                $input['address']['postal_code'] = preg_replace('/[\s-]/', '', $input['address']['postal_code']);
            }

            if (is_string($input['address']['state'] ?? null)) {
                $input['address']['state'] = strtoupper($input['address']['state']);
            }
        }

        foreach (['additional_phones', 'additional_emails'] as $field) {
            if (! is_array($input[$field] ?? null)) {
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

            $input[$field] = array_values(array_map($normalize, $input[$field]));
        }

        return $input;
    }
}
