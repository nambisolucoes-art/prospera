<?php

namespace App\Domains\Usuarios\Application\Data;

use App\Domains\Usuarios\Domain\Enums\UserRole;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class ProvisionUserData
{
    private function __construct(public CreateUserData $user, public UserRole $role) {}

    /** @param array<string, mixed> $input */
    public static function fromInput(#[\SensitiveParameter] array $input, bool $tenant): self
    {
        $rules = CreateUserData::rules();
        if ($tenant) {
            $rules['role'] = ['required', Rule::in([UserRole::AccountAdministrator->value, UserRole::AccountMember->value])];
        }

        $data = Validator::validate(CreateUserData::normalize($input), $rules, [
            ...CreateUserData::messages(),
            'role.required' => 'Informe o papel na conta.',
            'role.in' => 'Informe um papel válido para a conta.',
        ]);

        return new self(
            CreateUserData::fromValidated($data),
            $tenant ? UserRole::from($data['role']) : UserRole::PlatformAdministrator,
        );
    }
}
