<?php

namespace App\Domains\Usuarios\Application\Data;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final readonly class UpdateProfileData
{
    private function __construct(public string $name, public string $email) {}

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input, User $user): self
    {
        if (is_string($input['name'] ?? null)) {
            $input['name'] = trim($input['name']);
        }

        if (is_string($input['email'] ?? null)) {
            $email = trim($input['email']);
            $input['email'] = config('fortify.lowercase_usernames') ? Str::lower($email) : $email;
        }

        $data = Validator::validate($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($user)],
        ], [
            'name.required' => 'Informe o nome.',
            'email.required' => 'Informe o e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'email.unique' => 'Já existe um usuário com este e-mail.',
        ]);

        return new self($data['name'], $data['email']);
    }
}
