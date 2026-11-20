<?php

namespace App\Domains\Organizacao\Application\Data;

use App\Domains\Organizacao\Domain\DTOs\OrganizationCode;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Support\TaxDocument;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class SaveEstablishmentData
{
    private const FIELDS = [
        'name',
        'trade_name',
        'code',
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

    /** @param array<string,mixed> $values */
    private function __construct(public array $values) {}

    /** @param array<string,mixed> $input */
    public static function fromInput(array $input, Company $company, ?Establishment $existing): self
    {
        // Campos ausentes no envio mantêm o valor atual da unidade.
        $current = $existing === null ? ['active' => true] : [
            ...$existing->only(self::FIELDS),
            'classification' => $existing->legalClassification(),
            'active' => $existing->active,
        ];

        $values = self::normalize(array_replace($current, array_intersect_key($input, array_flip(self::FIELDS))));

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

        $uniqueDocument = Rule::unique('establishments', 'tax_document')
            ->where('account_id', $company->account_id)
            ->ignore($existing?->id);

        $uniqueHeadquarters = Rule::unique('establishments', 'company_id')
            ->where('is_headquarters', true)
            ->ignore($existing?->id);

        $rules = [
            'classification' => [
                'required',
                Rule::in(['headquarters', 'branch', 'unregistered']),
                $classificationRule,
            ],
            'tax_document' => $registered
                ? ['bail', 'required', 'string', $sameRoot, $uniqueDocument]
                : ['nullable'],
            'company_id' => ($values['classification'] ?? null) === 'headquarters' ? [$uniqueHeadquarters] : [],
            'active' => ['required', 'boolean'],
        ];

        if ($existing === null) {
            $rules['code'] = self::codeRules($company->account_id);
        }

        $validated = self::validate([...$values, 'company_id' => $company->id], $rules);

        return new self(self::values($validated, $registered));
    }

    /**
     * Unidade principal criada com a empresa (seção 6.5): o CNPJ completo informado na empresa é o dela.
     *
     * @param  array<string,mixed>  $input
     */
    public static function forPrimary(array $input, ?TaxDocument $companyDocument): self
    {
        $values = self::normalize(array_intersect_key($input, array_flip(self::FIELDS)));
        $registered = $companyDocument?->isCnpj() === true;

        // O código da unidade principal é gerado a partir do código da empresa.
        unset($values['code']);

        $values['tax_document'] = $registered ? $companyDocument->value() : null;
        $values['classification'] = $registered ? ($values['classification'] ?? null) : 'unregistered';
        $values['active'] = true;
        $values['name'] ??= self::primaryName($values['address']['city'] ?? null);

        $validated = self::validate($values, [
            'classification' => ['required', Rule::in($registered ? ['headquarters', 'branch'] : ['unregistered'])],
            'tax_document' => ['nullable', 'string'],
            'active' => ['boolean'],
        ]);

        return new self(self::values($validated, $registered));
    }

    public static function primaryName(?string $city): string
    {
        return $city === null ? 'Unidade principal' : 'Unidade principal ('.$city.')';
    }

    /** @return list<mixed> */
    public static function codeRules(int $accountId): array
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
            Rule::unique('establishments', 'code')->where('account_id', $accountId),
        ];
    }

    /**
     * @param  array<string,mixed>  $values
     * @return array<string,mixed>
     */
    private static function normalize(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($value)) {
                $values[$key] = trim($value) === '' ? null : trim($value);
            }
        }

        if (is_string($values['code'] ?? null)) {
            $values['code'] = mb_strtolower($values['code']);
        }

        if (is_string($values['tax_document'] ?? null)) {
            $values['tax_document'] = TaxDocument::normalize($values['tax_document']) ?: null;
        }

        foreach (['phone', 'whatsapp'] as $field) {
            if (is_string($values[$field] ?? null)) {
                $values[$field] = preg_replace('/[.\/()\s-]+/', '', $values[$field]);
            }
        }

        if (is_string($values['email'] ?? null)) {
            $values['email'] = mb_strtolower($values['email']);
        }

        if (is_array($values['address'] ?? null)) {
            foreach ($values['address'] as $key => $value) {
                if (is_string($value)) {
                    $values['address'][$key] = trim($value) === '' ? null : trim($value);
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

        return $values;
    }

    /**
     * @param  array<string,mixed>  $values
     * @param  array<string,mixed>  $rules
     * @return array<string,mixed>
     */
    private static function validate(array $values, array $rules): array
    {
        return Validator::validate($values, [
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
            ...$rules,
        ], [
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
        ]);
    }

    /**
     * @param  array<string,mixed>  $validated
     * @return array<string,mixed>
     */
    private static function values(array $validated, bool $registered): array
    {
        $filled = static fn (?array $list): array => array_values(array_filter(
            $list ?? [],
            static fn (mixed $value): bool => filled($value),
        ));

        return [
            'name' => $validated['name'],
            'trade_name' => $validated['trade_name'] ?? null,
            'code' => $validated['code'] ?? null,
            'tax_document' => $registered ? $validated['tax_document'] : null,
            'state_registration' => $validated['state_registration'] ?? null,
            'municipal_registration' => $validated['municipal_registration'] ?? null,
            'is_headquarters' => $validated['classification'] === 'headquarters',
            'active' => (bool) $validated['active'],
            'address' => $validated['address'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'whatsapp' => $validated['whatsapp'] ?? null,
            'email' => $validated['email'] ?? null,
            'additional_phones' => $filled($validated['additional_phones'] ?? null),
            'additional_emails' => $filled($validated['additional_emails'] ?? null),
        ];
    }
}
