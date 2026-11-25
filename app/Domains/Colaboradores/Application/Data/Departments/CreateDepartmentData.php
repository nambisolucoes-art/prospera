<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Data\Departments;

use App\Domains\Colaboradores\Domain\Exceptions\CompanyNotAvailable;
use App\Domains\Organizacao\Domain\ValueObjects\NavigableCompany;
use App\Support\NormalizedName;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Cadastro de um departamento em uma ou mais empresas. Só vale uma empresa que o descritor da permissão cobre.
 */
final readonly class CreateDepartmentData
{
    /** @param list<NavigableCompany> $companies */
    public function __construct(
        public string $name,
        public bool $active,
        public array $companies,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     * @param  list<NavigableCompany>  $allowedCompanies
     *
     * @throws CompanyNotAvailable
     * @throws ValidationException
     */
    public static function fromInput(array $input, array $allowedCompanies): self
    {
        if (is_string($input['name'] ?? null)) {
            $input['name'] = NormalizedName::display($input['name']);
        }

        // Com uma única empresa autorizada, a escolha é dispensada.
        if (! array_key_exists('companies', $input) && count($allowedCompanies) === 1) {
            $input['companies'] = [$allowedCompanies[0]->code];
        }

        $values = Validator::validate($input, self::rules(), self::messages());

        $companies = [];

        foreach (array_unique($values['companies']) as $code) {
            $company = collect($allowedCompanies)->first(fn (NavigableCompany $item) => $item->code === $code);

            // Empresa fora do acesso responde como inexistente.
            if ($company === null) {
                throw new CompanyNotAvailable;
            }

            $companies[] = $company;
        }

        self::ensureNameIsAvailable($values['name'], $companies);

        return new self(
            name: $values['name'],
            active: (bool) ($values['active'] ?? true),
            companies: $companies,
        );
    }

    /** @return array<string,list<string>> */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'active' => ['sometimes', 'boolean'],
            'companies' => ['required', 'array', 'min:1', 'max:200'],
            'companies.*' => ['string', 'max:40'],
        ];
    }

    /** @return array<string,string> */
    public static function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do departamento.',
            'name.string' => 'Informe um nome válido.',
            'name.max' => 'O nome deve ter até 100 caracteres.',
            'active.boolean' => 'Selecione uma situação válida.',
            'companies.required' => 'Escolha ao menos uma empresa.',
            'companies.array' => 'Escolha ao menos uma empresa.',
            'companies.min' => 'Escolha ao menos uma empresa.',
            'companies.*.string' => 'Escolha empresas válidas.',
        ];
    }

    /**
     * A unicidade compara a chave sem acentos nem caixa, em cada empresa escolhida.
     *
     * @param  list<NavigableCompany>  $companies
     */
    private static function ensureNameIsAvailable(string $name, array $companies): void
    {
        $taken = DB::table('departments')
            ->join('companies', 'companies.id', '=', 'departments.company_id')
            ->whereIn('departments.company_id', array_map(fn (NavigableCompany $company) => $company->id, $companies))
            ->where('departments.normalized_name', NormalizedName::key($name))
            ->orderBy('companies.name')
            ->get(['companies.name', 'companies.trade_name']);

        if ($taken->isEmpty()) {
            return;
        }

        $names = $taken->map(fn (object $company) => $company->trade_name ?: $company->name)->implode(', ');

        throw ValidationException::withMessages([
            'name' => count($companies) === 1
                ? 'Já existe um departamento com este nome nesta empresa.'
                : 'Já existe um departamento com este nome em: '.$names.'.',
        ]);
    }
}
