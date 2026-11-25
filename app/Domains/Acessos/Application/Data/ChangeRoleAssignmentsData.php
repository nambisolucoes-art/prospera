<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Data;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class ChangeRoleAssignmentsData
{
    /**
     * @param  list<array{role_id:int,scope:string,company_id?:int|null,establishment_id?:int|null}>  $grants
     * @param  list<int>  $revocations
     */
    public function __construct(public array $grants, public array $revocations) {}

    /** @param array<string,mixed> $input */
    public static function fromInput(array $input): self
    {
        $values = Validator::validate($input, [
            'grants' => ['sometimes', 'array', 'max:100'],
            'grants.*.role_id' => ['required', 'integer', 'min:1'],
            'grants.*.scope' => ['required', Rule::in(['tenant', 'company', 'establishment'])],
            'grants.*.company_id' => ['nullable', 'integer', 'min:1'],
            'grants.*.establishment_id' => ['nullable', 'integer', 'min:1'],
            'revocations' => ['sometimes', 'array', 'max:100'],
            'revocations.*' => ['integer', 'distinct', 'min:1'],
        ]);

        return new self(array_values($values['grants'] ?? []), array_values(array_map('intval', $values['revocations'] ?? [])));
    }
}
