<?php

declare(strict_types=1);

namespace App\Domains\Usuarios\Application\Data;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/** Pedido de troca do e-mail de acesso, confirmado com a senha atual. */
final readonly class RequestAccessEmailChangeData
{
    public function __construct(public string $email) {}

    /** @param array<string,mixed> $input */
    public static function fromInput(array $input): self
    {
        if (is_string($input['email'] ?? null)) {
            $input['email'] = Str::lower(trim($input['email']));
        }

        $values = Validator::validate($input, [
            'email' => ['required', 'string', 'email', 'max:255'],
            'current_password' => ['required', 'current_password:web'],
        ]);

        return new self($values['email']);
    }
}
