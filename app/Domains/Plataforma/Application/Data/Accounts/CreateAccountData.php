<?php

declare(strict_types=1);

namespace App\Domains\Plataforma\Application\Data\Accounts;

use App\Domains\Organizacao\Application\Data\Companies\CreateCompanyData;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Domains\Usuarios\Application\Data\CreateUserData;
use App\Support\TaxDocument;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Cadastro de uma conta com o administrador e a primeira empresa (seção 6.5). */
final readonly class CreateAccountData
{
    private const STATES = 'AC AL AP AM BA CE DF ES GO MA MT MS MG PA PB PR PE PI RJ RN RS RO RR SC SP SE TO';

    /**
     * @param  array<string,mixed>|null  $details
     * @param  array<string,mixed>|null  $firstCompany
     */
    private function __construct(
        public string $name,
        public ?string $email,
        public ?string $plan,
        public string $personType,
        public string $document,
        public bool $active,
        public ?array $details,
        public CreateUserData $administrator,
        public bool $contractorIsCompany,
        public ?string $contractorClassification,
        public ?array $firstCompany,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     *
     * @throws ValidationException
     */
    public static function fromInput(#[\SensitiveParameter] array $input): self
    {
        $input = self::normalize($input);

        $validated = Validator::validate($input, self::rules($input), self::messages());

        foreach (['additional_phones', 'additional_emails'] as $field) {
            if (isset($validated['details'][$field])) {
                $validated['details'][$field] = array_values(array_filter(
                    $validated['details'][$field],
                    static fn (?string $value): bool => filled($value),
                ));
            }
        }

        return new self(
            name: $validated['name'],
            email: $validated['email'] ?? null,
            plan: $validated['plan'] ?? null,
            personType: $validated['person_type'],
            document: $validated['document'],
            active: (bool) ($validated['active'] ?? true),
            details: $validated['details'] ?? null,
            administrator: CreateUserData::fromValidated($validated['administrator']),
            // Só a escolha explícita do cadastro automático usa os dados do contratante na primeira empresa.
            contractorIsCompany: ($validated['organization_setup'] ?? null) === 'automatic',
            contractorClassification: $validated['contractor_classification'] ?? null,
            firstCompany: ($validated['organization_setup'] ?? null) === 'manual' ? $input['first_company'] : null,
        );
    }

    /** @throws ValidationException */
    public function companyFor(Client $account): CreateCompanyData
    {
        if ($this->firstCompany === null) {
            return CreateCompanyData::forProvisioning(
                $account,
                $this->contractorIsCompany,
                $this->contractorClassification,
            );
        }

        try {
            return CreateCompanyData::fromInput($this->firstCompany, $account->id);
        } catch (ValidationException $exception) {
            $errors = [];

            foreach ($exception->errors() as $field => $messages) {
                $errors['first_company.'.$field] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }
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

        if (is_array($input['administrator'] ?? null)) {
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
    public static function rules(#[\SensitiveParameter] array $input): array
    {
        $company = ($input['person_type'] ?? null) === 'company';

        $manual = ($input['organization_setup'] ?? null) === 'manual';

        // O CNPJ do contratante pode ser da matriz ou de uma filial: a classificação é sempre declarada.
        $contractorCnpj = $company && ($input['organization_setup'] ?? null) === 'automatic';

        return [
            'organization_setup' => ['sometimes', 'required', Rule::in(['automatic', 'manual'])],
            'first_company' => [Rule::excludeIf(! $manual), 'required', 'array:name,trade_name,person_type,document,primary'],
            'first_company.primary' => [Rule::excludeIf(! $manual), 'required', 'array'],
            'first_company.primary.name' => [Rule::excludeIf(! $manual), 'required', 'string', 'max:255'],
            'contractor_classification' => [
                Rule::excludeIf(! $contractorCnpj),
                'required',
                Rule::in(['headquarters', 'branch']),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'plan' => ['nullable', 'string', 'max:100'],
            'person_type' => ['required', Rule::in(['company', 'individual'])],
            'document' => [
                'bail',
                'required',
                'string',
                static function (string $attribute, string $value, Closure $fail) use ($company): void {
                    $document = $company ? TaxDocument::cnpj($value) : TaxDocument::cpf($value);

                    if ($document === null) {
                        $fail('Informe um CPF ou CNPJ válido para o tipo de pessoa selecionado.');
                    }
                },
                Rule::unique(Client::class, 'document'),
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
            'details.phone' => ['required', 'regex:/^\d{10,11}$/'],
            'administrator' => ['required', 'array:name,email,mode,password,password_confirmation'],
            ...CreateUserData::rules('administrator.'),
        ];
    }

    /** @return array<string,string|array<string,string>> */
    public static function messages(): array
    {
        return [
            ...CreateUserData::messages('administrator.'),
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
            'first_company.required' => 'Preencha os dados da primeira empresa.',
            'first_company.primary.required' => 'Preencha os dados do estabelecimento principal.',
            'first_company.primary.name.required' => 'Informe o nome do estabelecimento principal.',
            'contractor_classification.required' => 'Informe se o CNPJ do contratante é da matriz ou de uma filial.',
            'contractor_classification.in' => 'Selecione Matriz ou Filial.',
            'administrator.required' => 'Preencha os dados do administrador da conta.',
        ];
    }
}
