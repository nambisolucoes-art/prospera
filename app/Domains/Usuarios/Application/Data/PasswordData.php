<?php

namespace App\Domains\Usuarios\Application\Data;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

final readonly class PasswordData
{
    private function __construct(#[\SensitiveParameter] public string $password) {}

    /** @param array<string, mixed> $input */
    public static function fromInput(#[\SensitiveParameter] array $input, bool $confirmCurrent = false): self
    {
        $rules = ['password' => self::rules()];
        if ($confirmCurrent) {
            $rules['current_password'] = self::currentPasswordRules();
        }

        $data = Validator::validate($input, $rules, [
            'current_password.required' => 'Informe a senha atual.',
            'current_password.string' => 'Informe uma senha válida.',
            'current_password.current_password' => 'A senha atual está incorreta.',
            'password.required' => 'Informe a nova senha.',
            'password.string' => 'Informe uma senha válida.',
            'password.min' => 'A senha deve ter pelo menos :min caracteres.',
            'password.confirmed' => 'As senhas precisam ser iguais.',
        ]);

        return new self($data['password']);
    }

    /** @return list<mixed> */
    public static function rules(): array
    {
        return ['required', 'string', Password::default(), 'confirmed'];
    }

    /** @return list<string> */
    public static function currentPasswordRules(): array
    {
        return ['required', 'string', 'current_password'];
    }
}
