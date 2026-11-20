<?php

namespace App\Domains\Organizacao\Application\Data;

use App\Domains\Organizacao\Domain\DTOs\OrganizationCode;
use Illuminate\Support\Facades\Validator;

final readonly class WorkspaceSelectionData
{
    /** @param list<string> $codes */
    private function __construct(public array $codes) {}

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $validated = Validator::make($input, [
            'unidades' => ['required', 'array', 'list'],
            'unidades.*' => ['required', 'string', 'distinct', 'regex:'.OrganizationCode::PATTERN],
        ], [
            'unidades.required' => 'Selecione pelo menos um estabelecimento.',
            'unidades.*' => 'Informe códigos de estabelecimentos válidos e sem repetição.',
        ])->validate();

        return new self($validated['unidades']);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<string>|null
     */
    public static function fromQuery(array $query): ?array
    {
        if (! array_key_exists('unidades', $query)) {
            return null;
        }

        abort_unless(is_string($query['unidades']), 404);
        $codes = explode(',', $query['unidades']);
        abort_if(array_filter($codes, OrganizationCode::isValid(...)) !== $codes, 404);

        return array_values(array_unique($codes));
    }
}
