<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Data\JobTitles;

use App\Support\NormalizedName;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Edição de uma função. A empresa do registro não muda: o nome é único dentro dela.
 */
final readonly class UpdateJobTitleData
{
    public function __construct(
        public string $name,
        public ?bool $active,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     *
     * @throws ValidationException
     */
    public static function fromInput(array $input, int $companyId, int $id): self
    {
        if (is_string($input['name'] ?? null)) {
            $input['name'] = NormalizedName::display($input['name']);
        }

        $values = Validator::validate($input, self::rules(), self::messages());

        $taken = DB::table('job_titles')
            ->where('company_id', $companyId)
            ->where('normalized_name', NormalizedName::key($values['name']))
            ->where('id', '!=', $id)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['name' => 'Já existe uma função com este nome nesta empresa.']);
        }

        return new self(
            name: $values['name'],
            active: array_key_exists('active', $values) ? (bool) $values['active'] : null,
        );
    }

    /** @return array<string,list<string>> */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string,string> */
    public static function messages(): array
    {
        return [
            'name.required' => 'Informe o nome da função.',
            'name.string' => 'Informe um nome válido.',
            'name.max' => 'O nome deve ter até 100 caracteres.',
            'active.boolean' => 'Selecione uma situação válida.',
        ];
    }
}
