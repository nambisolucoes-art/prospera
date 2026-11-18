<?php

namespace App\Domains\Usuarios\Application\Data;

use App\Models\User;
use Illuminate\Support\Facades\Validator;

final readonly class DeleteProfileData
{
    private function __construct(public User $user) {}

    /** @param array<string, mixed> $input */
    public static function fromInput(#[\SensitiveParameter] array $input, User $user): self
    {
        Validator::validate($input, ['password' => PasswordData::currentPasswordRules()], [
            'password.required' => 'Informe a senha atual.',
            'password.string' => 'Informe uma senha válida.',
            'password.current_password' => 'A senha atual está incorreta.',
        ]);

        return new self($user);
    }
}
