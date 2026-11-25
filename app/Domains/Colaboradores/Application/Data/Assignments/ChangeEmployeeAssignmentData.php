<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Data\Assignments;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Troca de função ou departamento a partir de uma data, preservando o histórico do período anterior. */
final readonly class ChangeEmployeeAssignmentData
{
    public function __construct(
        public string $effectiveOn,
        public ?int $departmentId,
        public ?int $jobTitleId,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     *
     * @throws ValidationException
     */
    public static function fromInput(array $input, AssignmentOptions $options): self
    {
        foreach (['department_id', 'job_title_id'] as $field) {
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

        return new self($values['effective_on'], $departmentId, $jobTitleId);
    }

    /** @return array<string,list<string>> */
    public static function rules(): array
    {
        return [
            'effective_on' => ['required', 'date_format:Y-m-d'],
            'department_id' => ['nullable', 'integer', 'min:1'],
            'job_title_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string,string> */
    public static function messages(): array
    {
        return [
            'effective_on.required' => 'Informe a data da mudança.',
            'effective_on.date_format' => 'Informe uma data válida.',
        ];
    }
}
