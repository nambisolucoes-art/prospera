<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Data;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class RoleAvailabilityData
{
    /**
     * @param  list<int>  $companies
     * @param  list<int>  $establishments
     * @param  list<int>  $confirmedRevocations
     */
    private function __construct(
        public string $mode,
        public array $companies,
        public array $establishments,
        public array $confirmedRevocations,
    ) {}

    /** @param array<string,mixed> $input */
    public static function fromInput(array $input, int $accountId, string $default = 'tenant'): self
    {
        $input['availability'] ??= $default;

        $validated = Validator::validate($input, [
            'availability' => ['required', Rule::in(['tenant', 'restricted'])],
            'available_companies' => ['sometimes', 'array'],
            'available_companies.*' => ['integer', 'distinct', Rule::exists('companies', 'id')->where('account_id', $accountId)],
            'available_establishments' => ['sometimes', 'array'],
            'available_establishments.*' => [
                'integer',
                'distinct',
                Rule::exists('establishments', 'id')->where('account_id', $accountId),
            ],
            'confirmed_revocations' => ['sometimes', 'array'],
            'confirmed_revocations.*' => ['integer', 'distinct'],
        ]);

        $companies = array_values(array_map('intval', $validated['available_companies'] ?? []));
        $establishments = array_values(array_map('intval', $validated['available_establishments'] ?? []));
        $confirmed = array_map('intval', $validated['confirmed_revocations'] ?? []);
        sort($confirmed);

        if ($validated['availability'] === 'restricted' && $companies === [] && $establishments === []) {
            throw ValidationException::withMessages([
                'availability' => 'Selecione ao menos uma empresa ou um estabelecimento.',
            ]);
        }

        return new self($validated['availability'], $companies, $establishments, $confirmed);
    }
}
