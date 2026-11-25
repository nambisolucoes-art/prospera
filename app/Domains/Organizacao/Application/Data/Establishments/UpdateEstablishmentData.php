<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Data\Establishments;

use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Support\ContactInput;
use App\Support\TaxDocument;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Edição de um estabelecimento. Campos ausentes no envio mantêm o valor atual da unidade. */
final readonly class UpdateEstablishmentData
{
    private const FIELDS = [
        'name',
        'trade_name',
        'classification',
        'tax_document',
        'state_registration',
        'municipal_registration',
        'active',
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
    public static function fromInput(array $input, Establishment $existing): self
    {
        $company = $existing->company;

        $current = [
            ...$existing->only(self::FIELDS),
            'classification' => $existing->legalClassification(),
            'active' => $existing->active,
        ];

        $values = self::normalize(array_replace($current, array_intersect_key($input, array_flip(self::FIELDS))));

        $validated = Validator::validate(
            [...$values, 'company_id' => $company->id],
            self::rules($company, $existing, $values),
            self::messages(),
        );

        $registered = in_array($values['classification'], ['headquarters', 'branch'], true);

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

    /**
     * @param  array<string,mixed>  $values
     * @return array<string,list<mixed>>
     */
    public static function rules(Company $company, Establishment $existing, array $values): array
    {
        $companyDocument = $company->taxDocument();
        $registered = in_array($values['classification'] ?? null, ['headquarters', 'branch'], true);

        $sameRoot = function (string $attribute, mixed $value, Closure $fail) use ($company): void {
            $document = is_string($value) ? TaxDocument::cnpj($value) : null;

            if ($document === null) {
                $fail('Informe um CNPJ válido.');

                return;
            }

            if (! $company->admitsEstablishmentTaxDocument($document)) {
                $fail('O CNPJ deve ter a mesma raiz da empresa ('.$company->taxDocument()?->formatted().').');
            }
        };

        $classificationRule = function (string $attribute, mixed $value, Closure $fail) use ($companyDocument): void {
            if ($value === 'unregistered' || $companyDocument?->root() !== null) {
                return;
            }

            $fail($companyDocument === null
                ? 'Informe o CNPJ da empresa antes de cadastrar matriz ou filial.'
                : 'Empresas identificadas por CPF não têm matriz nem filial.');
        };

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
            'classification' => ['required', Rule::in(['headquarters', 'branch', 'unregistered']), $classificationRule],
            'tax_document' => $registered
                ? ['bail', 'required', 'string', $sameRoot, Rule::unique('establishments', 'tax_document')->where('account_id', $company->account_id)->ignore($existing->id)]
                : ['nullable'],
            'company_id' => ($values['classification'] ?? null) === 'headquarters'
                ? [Rule::unique('establishments', 'company_id')->where('is_headquarters', true)->ignore($existing->id)]
                : [],
            'active' => ['required', 'boolean'],
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

    /**
     * @param  array<string,mixed>  $values
     * @return array<string,mixed>
     */
    private static function normalize(array $values): array
    {
        $values = ContactInput::normalize($values);

        if (is_string($values['code'] ?? null)) {
            $values['code'] = mb_strtolower($values['code']);
        }

        if (is_string($values['tax_document'] ?? null)) {
            $values['tax_document'] = TaxDocument::normalize($values['tax_document']) ?: null;
        }

        return $values;
    }
}
