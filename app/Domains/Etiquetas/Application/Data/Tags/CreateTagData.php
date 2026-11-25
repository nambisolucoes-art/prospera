<?php

declare(strict_types=1);

namespace App\Domains\Etiquetas\Application\Data\Tags;

use App\Domains\Etiquetas\Domain\Models\Tag;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class CreateTagData
{
    public function __construct(
        public string $name,
        public string $module,
        public string $color,
        public bool $active,
    ) {}

    /** @param array<string,mixed> $input */
    public static function fromInput(array $input, int $accountId): self
    {
        if (is_string($input['name'] ?? null)) {
            $input['name'] = trim($input['name']);
        }

        $values = Validator::validate($input, self::rules($accountId, $input), self::messages());

        return new self(
            name: $values['name'],
            module: $values['module'],
            color: $values['color'],
            active: (bool) ($values['active'] ?? true),
        );
    }

    /**
     * @param  array<string,mixed>  $input
     * @return array<string,list<mixed>>
     */
    public static function rules(int $accountId, array $input): array
    {
        $uniqueName = Rule::unique('tags')
            ->where('account_id', $accountId)
            ->where('module', is_string($input['module'] ?? null) ? $input['module'] : '');

        return [
            'active' => ['sometimes', 'boolean'],
            'name' => ['required', 'string', 'max:100', $uniqueName],
            'module' => ['required', Rule::in(Tag::COMMERCIAL_MODULES)],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    /** @return array<string,string> */
    public static function messages(): array
    {
        return [
            'active.boolean' => 'Selecione uma situação válida.',
            'name.required' => 'Informe o nome da etiqueta.',
            'name.string' => 'Informe um nome válido.',
            'name.max' => 'O nome deve ter até 100 caracteres.',
            'name.unique' => 'Já existe uma etiqueta com este nome no módulo.',
            'module.required' => 'Selecione o módulo.',
            'module.in' => 'Selecione um módulo válido.',
            'color.required' => 'Informe a cor.',
            'color.regex' => 'Informe uma cor hexadecimal válida.',
        ];
    }
}
