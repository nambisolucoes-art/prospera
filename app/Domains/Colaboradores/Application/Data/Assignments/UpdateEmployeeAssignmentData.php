<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Data\Assignments;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Correção de uma lotação existente: função, departamento e datas. A unidade não muda. */
final readonly class UpdateEmployeeAssignmentData
{
    public function __construct(
        public ?int $departmentId,
        public ?int $jobTitleId,
        public string $startsOn,
        public ?string $endsOn,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     *
     * @throws ValidationException
     */
    public static function fromInput(array $input, AssignmentOptions $options): self
    {
        foreach (['ends_on', 'department_id', 'job_title_id'] as $field) {
            if (($input[$field] ?? null) === '') {
                $input[$field] = null;
            }
        }

        $values = Validator::validate($input, self::rules(), self::messages());

        $departmentId = isset($values['department_id']) ? (int) $values['department_id'] : null;
        $jobTitleId = isset($values['job_title_id']) ? (int) $values['job_title_id'] : null;

        $errors = [];

        if ($departmentId !== null && ! $options->hasDepartment($departmentId)) {
            $errors['department_id'] = 'Escolha um departamento ativo desta empresa.';
        }

        if ($jobTitleId !== null && ! $options->hasJobTitle($jobTitleId)) {
            $errors['job_title_id'] = 'Escolha uma função ativa desta empresa.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return new self($departmentId, $jobTitleId, $values['starts_on'], $values['ends_on'] ?? null);
    }

    /** @return array<string,list<string>> */
    public static function rules(): array
    {
        return [
            'department_id' => ['nullable', 'integer', 'min:1'],
            'job_title_id' => ['nullable', 'integer', 'min:1'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ];
    }

    /** @return array<string,string> */
    public static function messages(): array
    {
        return [
            'starts_on.required' => 'Informe a data de início.',
            'starts_on.date_format' => 'Informe uma data de início válida.',
            'ends_on.date_format' => 'Informe uma data final válida.',
            'ends_on.after_or_equal' => 'A data final não pode ser anterior ao início.',
        ];
    }
}
