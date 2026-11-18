<?php

namespace App\Domains\Plataforma\Application\Data;

use Illuminate\Support\Facades\Validator;

final readonly class SavePointData
{
    private function __construct(public string $name, public bool $active) {}

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        if (is_string($input['name'] ?? null)) {
            $input['name'] = trim($input['name']);
        }

        $validated = Validator::validate($input, [
            'name' => ['required', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
        ], [
            'name.required' => 'Informe o nome do ponto.',
            'name.string' => 'Informe um nome válido para o ponto.',
            'name.max' => 'O nome do ponto deve ter até :max caracteres.',
            'active.required' => 'Informe a situação do ponto.',
            'active.boolean' => 'Informe uma situação válida para o ponto.',
        ]);

        return new self($validated['name'], (bool) $validated['active']);
    }
}
