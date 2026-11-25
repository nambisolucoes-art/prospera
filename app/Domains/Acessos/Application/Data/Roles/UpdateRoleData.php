<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Data\Roles;

use App\Domains\Acessos\Domain\Models\Role;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Edição de um cargo da conta. A abrangência ausente no envio mantém a atual. */
final readonly class UpdateRoleData
{
    /** @param list<int> $permissionIds */
    public function __construct(
        public string $name,
        public array $permissionIds,
        public RoleAvailabilityData $availability,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     *
     * @throws ValidationException
     */
    public static function fromInput(array $input, Role $role): self
    {
        if (is_string($input['name'] ?? null)) {
            $input['name'] = trim($input['name']);
        }

        $values = Validator::validate($input, self::rules($role->account_id, $role->id), self::messages());

        $permissionIds = array_map('intval', $values['permission_ids'] ?? []);
        sort($permissionIds);

        return new self(
            name: $values['name'],
            permissionIds: $permissionIds,
            availability: RoleAvailabilityData::fromInput($input, $role->account_id, $role->availability),
        );
    }

    /** @return array<string,list<mixed>> */
    public static function rules(int $accountId, int $ignoreId): array
    {
        $uniqueName = Rule::unique('roles')->where('account_id', $accountId)->ignore($ignoreId);

        // Cargos da conta só recebem permissões de conta e pessoais.
        $availablePermission = Rule::exists('permissions', 'id')
            ->where('is_system', true)
            ->whereIn('scope', ['account', 'self'])
            ->where(fn ($query) => $query
                ->whereNull('account_id')
                ->orWhere('account_id', $accountId));

        return [
            'name' => ['required', 'string', 'max:100', $uniqueName],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'distinct', $availablePermission],
        ];
    }

    /** @return array<string,string> */
    public static function messages(): array
    {
        return [
            'name.string' => 'Informe um nome válido.',
            'name.required' => 'Informe o nome do cargo.',
            'name.unique' => 'Já existe um cargo com este nome.',
            'name.max' => 'Use até 100 caracteres.',
            'permission_ids.array' => 'Selecione permissões válidas.',
            'permission_ids.*' => 'Selecione permissões disponíveis para esta conta.',
        ];
    }
}
