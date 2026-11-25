<?php

declare(strict_types=1);

namespace App\Domains\Usuarios\Application\Data;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/** Confirmação da troca do e-mail de acesso pelo link assinado enviado ao novo endereço. */
final readonly class ConfirmAccessEmailChangeData
{
    public function __construct(public string $email, public string $revision) {}

    /** @param array<string,mixed> $input */
    public static function fromInput(array $input): self
    {
        if (is_string($input['email'] ?? null)) {
            $input['email'] = Str::lower(trim($input['email']));
        }

        $values = Validator::validate($input, [
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        return new self($values['email'], is_string($input['revision'] ?? null) ? $input['revision'] : '');
    }
}
