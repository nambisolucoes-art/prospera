<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Data\Assignments;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Encerramento de uma lotação, com a indicação da nova principal quando necessário. */
final readonly class EndEmployeeAssignmentData
{
    public function __construct(
        public string $endsOn,
        public ?int $newPrimaryId,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     *
     * @throws ValidationException
     */
    public static function fromInput(array $input): self
    {
        if (($input['new_primary_id'] ?? null) === '') {
            $input['new_primary_id'] = null;
        }

        $values = Validator::validate($input, self::rules(), self::messages());

        return new self(
            endsOn: $values['ends_on'],
            newPrimaryId: isset($values['new_primary_id']) ? (int) $values['new_primary_id'] : null,
        );
    }

    /** @return array<string,list<string>> */
    public static function rules(): array
    {
        return [
            'ends_on' => ['required', 'date_format:Y-m-d'],
            'new_primary_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string,string> */
    public static function messages(): array
    {
        return [
            'ends_on.required' => 'Informe a data final.',
            'ends_on.date_format' => 'Informe uma data final válida.',
        ];
    }
}
