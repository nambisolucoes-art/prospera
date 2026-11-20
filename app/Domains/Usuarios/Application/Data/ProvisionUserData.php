<?php

namespace App\Domains\Usuarios\Application\Data;

use App\Domains\Usuarios\Domain\Enums\UserRole;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class ProvisionUserData
{
    private function __construct(public CreateUserData $user, public UserRole $role, public ?int $roleId = null) {}

    /** @param array<string, mixed> $input */
    public static function fromInput(#[\SensitiveParameter] array $input, bool $tenant, ?int $accountId = null): self
    {
        $rules = CreateUserData::rules();
        if ($tenant) {
            $rules['role_id'] = ['required', 'integer', Rule::exists('roles', 'id')->where('account_id', $accountId)];
        }

        $data = Validator::validate(CreateUserData::normalize($input), $rules, [
            ...CreateUserData::messages(),
            'role_id.required' => 'Selecione o cargo na conta.',
            'role_id.exists' => 'Selecione um cargo desta conta.',
            'role_id.integer' => 'Selecione um cargo válido.',
            'role.in' => 'Informe um papel válido para a conta.',
        ]);

        return new self(
            CreateUserData::fromValidated($data),
            $tenant ? UserRole::AccountMember : UserRole::PlatformAdministrator,
            $tenant ? (int) $data['role_id'] : null,
        );
    }
}
