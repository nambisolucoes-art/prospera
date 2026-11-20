<?php

namespace App\Domains\Acessos\Application\Data;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class SaveRoleData
{
    /** @param array<string,mixed> $values */
    public function __construct(public array $values) {}

    /** @param array<string,mixed> $input */
    public static function fromInput(array $input, int $accountId, ?int $id): self
    {
        if (is_string($input['name'] ?? null)) {
            $input['name'] = trim($input['name']);
        }

        $uniqueName = Rule::unique('roles')
            ->where('account_id', $accountId)
            ->ignore($id);

        // Cargos da conta só recebem permissões de conta e pessoais.
        $availablePermission = Rule::exists('permissions', 'id')
            ->where('is_system', true)
            ->whereIn('scope', ['account', 'self'])
            ->where(fn ($query) => $query
                ->whereNull('account_id')
                ->orWhere('account_id', $accountId));

        $values = Validator::validate($input, [
            'name' => ['required', 'string', 'max:100', $uniqueName],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'distinct', $availablePermission],
        ], [
            'name.string' => 'Informe um nome válido.',
            'name.required' => 'Informe o nome do cargo.',
            'name.unique' => 'Já existe um cargo com este nome.',
            'name.max' => 'Use até 100 caracteres.',
            'permission_ids.array' => 'Selecione permissões válidas.',
            'permission_ids.*' => 'Selecione permissões disponíveis para esta conta.',
        ]);

        return new self($values);
    }
}
