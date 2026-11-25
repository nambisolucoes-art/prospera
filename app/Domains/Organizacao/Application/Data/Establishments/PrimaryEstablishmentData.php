<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Data\Establishments;

use App\Support\ContactInput;
use App\Support\TaxDocument;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Unidade principal criada junto com a empresa (seção 6.5): o CNPJ completo informado na empresa é o dela e
 * o código é gerado a partir do código da empresa.
 */
final readonly class PrimaryEstablishmentData
{
    private const FIELDS = [
        'name',
        'trade_name',
        'classification',
        'state_registration',
        'municipal_registration',
        'address',
        'phone',
        'whatsapp',
        'email',
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
        public ?string $tradeName,
        public ?string $taxDocument,
        public ?string $stateRegistration,
        public ?string $municipalRegistration,
        public bool $isHeadquarters,
        public bool $active,
        public ?array $address,
        public ?string $phone,
        public ?string $whatsapp,
        public ?string $email,
        public array $additionalPhones,
        public array $additionalEmails,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     *
     * @throws ValidationException
     */
    public static function fromInput(array $input, ?TaxDocument $companyDocument): self
    {
        $values = ContactInput::normalize(array_intersect_key($input, array_flip(self::FIELDS)));
        $registered = $companyDocument?->isCnpj() === true;

        $values['tax_document'] = $registered ? $companyDocument->value() : null;
        $values['classification'] = $registered ? ($values['classification'] ?? null) : 'unregistered';
        $values['active'] = true;
        $values['name'] ??= self::defaultName($values['address']['city'] ?? null);

        $validated = Validator::validate($values, self::rules($registered), self::messages());

        return new self(
            name: $validated['name'],
            tradeName: $validated['trade_name'] ?? null,
            taxDocument: $registered ? $validated['tax_document'] : null,
            stateRegistration: $validated['state_registration'] ?? null,
            municipalRegistration: $validated['municipal_registration'] ?? null,
            isHeadquarters: $validated['classification'] === 'headquarters',
            active: (bool) $validated['active'],
            address: $validated['address'] ?? null,
            phone: $validated['phone'] ?? null,
            whatsapp: $validated['whatsapp'] ?? null,
            email: $validated['email'] ?? null,
            additionalPhones: ContactInput::filled($validated['additional_phones'] ?? null),
            additionalEmails: ContactInput::filled($validated['additional_emails'] ?? null),
        );
    }

    public static function defaultName(?string $city): string
    {
        return $city === null ? 'Unidade principal' : 'Unidade principal ('.$city.')';
    }

    /** @return array<string,list<mixed>> */
    public static function rules(bool $registered): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'state_registration' => ['nullable', 'string', 'max:30'],
            'municipal_registration' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'array:postal_code,street,number,complement,neighborhood,city,state'],
            'address.postal_code' => ['nullable', 'regex:/^\d{8}$/'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.number' => ['nullable', 'string', 'max:30'],
            'address.complement' => ['nullable', 'string', 'max:150'],
            'address.neighborhood' => ['nullable', 'string', 'max:150'],
            'address.city' => ['nullable', 'string', 'max:150'],
            'address.state' => ['nullable', Rule::in(explode(' ', self::STATES))],
            'phone' => ['nullable', 'regex:/^\d{10,11}$/'],
            'whatsapp' => ['nullable', 'regex:/^\d{10,11}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'additional_phones' => ['sometimes', 'nullable', 'array', 'list', 'max:20'],
            'additional_phones.*' => ['nullable', 'string', 'regex:/^\d{10,11}$/'],
            'additional_emails' => ['sometimes', 'nullable', 'array', 'list', 'max:20'],
            'additional_emails.*' => ['nullable', 'string', 'email', 'max:255'],
            'classification' => ['required', Rule::in($registered ? ['headquarters', 'branch'] : ['unregistered'])],
            'tax_document' => ['nullable', 'string'],
            'active' => ['boolean'],
        ];
    }

    /** @return array<string,string> */
    public static function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do estabelecimento.',
            'classification.required' => 'Selecione a classificação jurídica.',
            'classification.in' => 'Selecione Matriz, Filial ou Unidade sem CNPJ próprio.',
            'tax_document.required' => 'Informe o CNPJ da matriz ou da filial.',
            'tax_document.unique' => 'Já existe um estabelecimento com este CNPJ nesta conta.',
            'company_id.unique' => 'Esta empresa já tem uma matriz. Altere a classificação da matriz atual antes.',
            'code.unique' => 'Já existe um estabelecimento com este código nesta conta.',
            'active.required' => 'Selecione a situação.',
            'active.boolean' => 'Selecione uma situação válida.',
            'address.postal_code.regex' => 'Informe um CEP com 8 dígitos.',
            'address.state.in' => 'Selecione um estado válido.',
            'address.array' => 'Informe um endereço válido.',
            'phone.regex' => 'Informe um telefone com DDD e 10 ou 11 dígitos.',
            'whatsapp.regex' => 'Informe um WhatsApp com DDD e 10 ou 11 dígitos.',
            'email.email' => 'Informe um e-mail válido.',
            'additional_phones.max' => 'Adicione até 20 telefones adicionais.',
            'additional_phones.*.regex' => 'Informe um telefone com DDD e 10 ou 11 dígitos.',
            'additional_emails.max' => 'Adicione até 20 e-mails adicionais.',
            'additional_emails.*.email' => 'Informe um e-mail válido.',
            '*.string' => 'Informe um texto válido.',
            '*.max' => 'O valor informado excede o tamanho permitido.',
        ];
    }
}
