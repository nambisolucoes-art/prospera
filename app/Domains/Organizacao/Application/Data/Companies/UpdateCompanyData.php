<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Data\Companies;

use App\Domains\Organizacao\Domain\Models\Company;
use App\Support\TaxDocument;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Edição de uma empresa. Campos ausentes no envio mantêm o valor atual. O código é imutável (DA-28). */
final readonly class UpdateCompanyData
{
    private const FIELDS = ['name', 'trade_name', 'person_type', 'document', 'tax_regime', 'main_cnae', 'active'];

    public function __construct(
        public string $name,
        public ?string $tradeName,
        public ?string $personType,
        public ?string $taxRoot,
        public ?string $individualDocument,
        public ?string $taxRegime,
        public ?string $mainCnae,
        public bool $active,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     *
     * @throws ValidationException
     */
    public static function fromInput(array $input, Company $existing): self
    {
        $current = [
            ...$existing->only(array_diff(self::FIELDS, ['document'])),
            'document' => $existing->tax_root ?? $existing->individual_document,
            'active' => $existing->active,
        ];

        $values = array_replace($current, array_intersect_key($input, array_flip(self::FIELDS)));
        $accountId = $existing->account_id;

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
        ], self::rules($accountId, $existing->id, $document, $values), self::messages());

        $errors = $validator->errors()->toArray();

        // A unicidade é verificada nas colunas fiscais, mas o erro pertence ao campo do formulário.
        foreach (['tax_root', 'individual_document'] as $field) {
            if (isset($errors[$field])) {
                $errors['document'] = [...($errors['document'] ?? []), ...$errors[$field]];
                unset($errors[$field]);
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
            taxRegime: $validated['tax_regime'] ?? null,
            mainCnae: $validated['main_cnae'] ?? null,
            active: (bool) $validated['active'],
        );
    }

    /**
     * @param  array<string,mixed>  $values
     * @return array<string,list<mixed>>
     */
    public static function rules(int $accountId, int $companyId, ?TaxDocument $document, array $values): array
    {
        $company = ($values['person_type'] ?? null) === 'company';

        $documentRule = function (string $attribute, mixed $value, Closure $fail) use ($document, $company): void {
            if ($document === null) {
                $fail($company ? 'Informe um CNPJ completo ou a raiz com 8 caracteres.' : 'Informe um CPF válido.');
            }
        };

        // A unicidade fiscal é avaliada só dentro da conta, sem revelar outras contas (6.7).
        $uniqueRoot = Rule::unique('companies', 'tax_root')->where('account_id', $accountId)->ignore($companyId);
        $uniqueIndividual = Rule::unique('companies', 'individual_document')->where('account_id', $accountId)->ignore($companyId);

        return [
            'name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'person_type' => ['required', Rule::in(['company', 'individual'])],
            'document' => ['bail', 'required', 'string', $documentRule],
            'tax_root' => ['nullable', $uniqueRoot],
            'individual_document' => ['nullable', $uniqueIndividual],
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
}
