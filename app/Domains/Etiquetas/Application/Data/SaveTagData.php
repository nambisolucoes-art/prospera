<?php

namespace App\Domains\Etiquetas\Application\Data;

use App\Domains\Etiquetas\Domain\Models\Tag;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class SaveTagData
{
    /** @param array<string,mixed> $values */
    public function __construct(public array $values) {}

    /** @param array<string,mixed> $input */
    public static function fromInput(array $input, int $accountId, ?int $id, ?string $existingModule = null): self
    {
        if (is_string($input['name'] ?? null)) {
            $input['name'] = trim($input['name']);
        }

        $uniqueName = Rule::unique('tags')
            ->where('account_id', $accountId)
            ->where('module', is_string($input['module'] ?? null) ? $input['module'] : '')
            ->ignore($id);

        $values = Validator::validate($input, [
            'active' => ['sometimes', 'boolean'],
            'name' => ['required', 'string', 'max:100', $uniqueName],
            'module' => ['required', Rule::in($existingModule === 'people' ? ['people'] : Tag::COMMERCIAL_MODULES)],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [
            'active.boolean' => 'Selecione uma situação válida.',
            'name.required' => 'Informe o nome da etiqueta.',
            'name.string' => 'Informe um nome válido.',
            'name.max' => 'O nome deve ter até 100 caracteres.',
            'name.unique' => 'Já existe uma etiqueta com este nome no módulo.',
            'module.required' => 'Selecione o módulo.',
            'module.in' => 'Selecione um módulo válido.',
            'color.required' => 'Informe a cor.',
            'color.regex' => 'Informe uma cor hexadecimal válida.',
        ]);

        return new self($values);
    }
}
