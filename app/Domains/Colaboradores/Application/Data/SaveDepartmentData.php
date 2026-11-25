<?php

namespace App\Domains\Colaboradores\Application\Data;

use App\Support\NormalizedName;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class SaveDepartmentData
{
    /** @param array<string,mixed> $values */
    public function __construct(public array $values) {}

    /**
     * @param  array<string,mixed>  $input
     * @param  list<int>  $companyIds
     */
    public static function fromInput(array $input, array $companyIds, ?int $id): self
    {
        if (is_string($input['name'] ?? null)) {
            $input['name'] = NormalizedName::display($input['name']);
        }

        $values = Validator::validate($input, [
            'name' => ['required', 'string', 'max:100'],
            'active' => ['sometimes', 'boolean'],
        ], [
            'name.required' => 'Informe o nome do departamento.',
            'name.string' => 'Informe um nome válido.',
            'name.max' => 'O nome deve ter até 100 caracteres.',
            'active.boolean' => 'Selecione uma situação válida.',
        ]);

        // A unicidade compara a chave sem acentos nem caixa, em cada empresa escolhida.
        $taken = DB::table('departments')
            ->join('companies', 'companies.id', '=', 'departments.company_id')
            ->whereIn('departments.company_id', $companyIds)
            ->where('departments.normalized_name', NormalizedName::key($values['name']))
            ->when($id !== null, fn ($query) => $query->where('departments.id', '!=', $id))
            ->orderBy('companies.name')
            ->get(['companies.name', 'companies.trade_name']);

        if ($taken->isNotEmpty()) {
            $names = $taken->map(fn (object $company) => $company->trade_name ?: $company->name)->implode(', ');

            throw ValidationException::withMessages([
                'name' => $taken->count() === 1 && count($companyIds) === 1
                    ? 'Já existe um departamento com este nome nesta empresa.'
                    : 'Já existe um departamento com este nome em: '.$names.'.',
            ]);
        }

        return new self($values);
    }
}
