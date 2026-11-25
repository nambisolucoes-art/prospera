<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Data\Companies;

use App\Domains\Organizacao\Application\Data\Establishments\PrimaryEstablishmentData;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\ValueObjects\OrganizationCode;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Support\TaxDocument;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Cadastro de uma empresa com a unidade principal (seção 6.5). */
final readonly class CreateCompanyData
{
    private const FIELDS = ['name', 'trade_name', 'person_type', 'document', 'code', 'tax_regime', 'main_cnae'];

    public function __construct(
        public string $name,
        public ?string $tradeName,
        public ?string $personType,
        public ?string $taxRoot,
        public ?string $individualDocument,
        public ?string $code,
        public ?string $taxRegime,
        public ?string $mainCnae,
        public ?TaxDocument $document,
        public PrimaryEstablishmentData $primary,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     *
     * @throws ValidationException
     */
    public static function fromInput(array $input, int $accountId): self
    {
        $values = array_intersect_key($input, array_flip(self::FIELDS));

        foreach ($values as $key => $value) {
            if (is_string($value)) {
                $values[$key] = trim($value) === '' ? null : trim($value);
            }
        }

        if (is_string($values['document'] ?? null)) {
            $values['document'] = TaxDocument::normalize($values['document']) ?: null;
        }

        if (is_string($values['main_cnae'] ?? null)) {
            $values['main_cnae'] = preg_replace('/\D/', '', $values['main_cnae']) ?: null;
        }

        if (is_string($values['code'] ?? null)) {
            $values['code'] = mb_strtolower($values['code']);
        }

        $values['active'] = true;

        $company = ($values['person_type'] ?? null) === 'company';

        $document = match (true) {
            ! is_string($values['document'] ?? null) => null,
            $company => TaxDocument::cnpj($values['document']) ?? TaxDocument::cnpjRoot($values['document']),
            default => TaxDocument::cpf($values['document']),
        };

        $validator = Validator::make([
            ...$values,
            'tax_root' => $document?->root(),
            'individual_document' => $document?->isCpf() ? $document->value() : null,
        ], self::rules($accountId, $document, $values), self::messages());

        $errors = $validator->errors()->toArray();

        // A unicidade é verificada nas colunas fiscais, mas o erro pertence ao campo do formulário.
        foreach (['tax_root', 'individual_document'] as $field) {
            if (isset($errors[$field])) {
                $errors['document'] = [...($errors['document'] ?? []), ...$errors[$field]];
                unset($errors[$field]);
            }
        }

        $primary = null;
        $primaryInput = is_array($input['primary'] ?? null) ? $input['primary'] : [];

        try {
            $primary = PrimaryEstablishmentData::fromInput($primaryInput, $document);
        } catch (ValidationException $exception) {
            // Os erros da unidade principal aparecem no grupo correspondente do formulário.
            foreach ($exception->errors() as $field => $messages) {
                $errors['primary.'.$field] = $messages;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $validated = $validator->validated();

        return new self(
            name: $validated['name'],
            tradeName: $validated['trade_name'] ?? null,
            personType: $validated['person_type'],
            taxRoot: $document?->root(),
            individualDocument: $document?->isCpf() ? $document->value() : null,
            code: $validated['code'] ?? null,
            taxRegime: $validated['tax_regime'] ?? null,
            mainCnae: $validated['main_cnae'] ?? null,
            document: $document,
            primary: $primary,
        );
    }

    /**
     * Primeira empresa de uma conta (seção 6.5). Os dados fiscais do contratante só são copiados quando o
     * operador declara que ele é a própria empresa administrada; caso contrário, a empresa nasce sem
     * documento e a conta completa a identificação depois. A classificação do CNPJ (matriz ou filial) é
     * informada pelo operador e nunca deduzida do documento.
     *
     * @throws ValidationException
     */
    public static function forProvisioning(
        Client $account,
        bool $contractorIsCompany,
        ?string $classification = null,
    ): self {
        $details = $account->details ?? [];
        $tradeName = is_string($details['trade_name'] ?? null) ? $details['trade_name'] : null;

        $document = null;

        if ($contractorIsCompany && $account->person_type === 'company') {
            $document = TaxDocument::cnpj($account->document);
        }

        if ($contractorIsCompany && $account->person_type === 'individual') {
            $document = TaxDocument::cpf($account->document);
        }

        $primaryInput = $contractorIsCompany ? [
            'classification' => $classification,
            'address' => $details['address'] ?? null,
            'phone' => $details['phone'] ?? null,
            'whatsapp' => $details['whatsapp'] ?? null,
            'email' => $account->email,
            'additional_phones' => $details['additional_phones'] ?? [],
            'additional_emails' => $details['additional_emails'] ?? [],
        ] : [];

        return new self(
            name: $account->name,
            tradeName: $contractorIsCompany ? $tradeName : null,
            personType: $document === null ? null : $account->person_type,
            taxRoot: $document?->root(),
            individualDocument: $document?->isCpf() ? $document->value() : null,
            code: null,
            taxRegime: null,
            mainCnae: null,
            document: $document,
            primary: PrimaryEstablishmentData::fromInput($primaryInput, $document),
        );
    }

    /**
     * @param  array<string,mixed>  $values
     * @return array<string,list<mixed>>
     */
    public static function rules(int $accountId, ?TaxDocument $document, array $values): array
    {
        $company = ($values['person_type'] ?? null) === 'company';

        $documentRule = function (string $attribute, mixed $value, Closure $fail) use ($document, $company): void {
            if ($document === null) {
                $fail($company ? 'Informe um CNPJ completo ou a raiz com 8 caracteres.' : 'Informe um CPF válido.');
            }
        };

        // A unicidade fiscal é avaliada só dentro da conta, sem revelar outras contas (6.7).
        $uniqueRoot = Rule::unique('companies', 'tax_root')->where('account_id', $accountId);
        $uniqueIndividual = Rule::unique('companies', 'individual_document')->where('account_id', $accountId);

        return [
            'name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'person_type' => ['required', Rule::in(['company', 'individual'])],
            'document' => ['bail', 'required', 'string', $documentRule],
            'tax_root' => ['nullable', $uniqueRoot],
            'individual_document' => ['nullable', $uniqueIndividual],
            'code' => self::codeRules($accountId),
            'tax_regime' => ['nullable', Rule::in(array_keys(Company::TAX_REGIMES))],
            'main_cnae' => ['nullable', 'regex:/^\d{7}$/'],
            'active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string,string> */
    public static function messages(): array
    {
        return [
            'name.required' => 'Informe a razão social ou o nome.',
            'person_type.required' => 'Selecione o tipo de pessoa.',
            'person_type.in' => 'Selecione Pessoa jurídica ou Pessoa física.',
            'document.required' => 'Informe o CNPJ ou o CPF da empresa.',
            'tax_root.unique' => 'Já existe uma empresa com esta raiz de CNPJ nesta conta.',
            'individual_document.unique' => 'Já existe uma empresa com este CPF nesta conta.',
            'code.unique' => 'Já existe uma empresa com este código nesta conta.',
            'tax_regime.in' => 'Selecione um regime tributário válido.',
            'main_cnae.regex' => 'Informe o CNAE com 7 dígitos.',
            'active.required' => 'Selecione a situação.',
            'active.boolean' => 'Selecione uma situação válida.',
            '*.string' => 'Informe um texto válido.',
            '*.max' => 'O valor informado excede o tamanho permitido.',
        ];
    }

    /** @return list<mixed> */
    private static function codeRules(int $accountId): array
    {
        return [
            'nullable',
            'string',
            'max:'.OrganizationCode::MAX_LENGTH,
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! OrganizationCode::isValid($value)) {
                    $fail('Use de 2 a 40 letras minúsculas sem acento, números e hífen, sem palavras reservadas.');
                }
            },
            Rule::unique('companies', 'code')->where('account_id', $accountId),
        ];
    }
}
