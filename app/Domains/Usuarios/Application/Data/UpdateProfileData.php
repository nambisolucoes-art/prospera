<?php

declare(strict_types=1);

namespace App\Domains\Usuarios\Application\Data;

use Illuminate\Support\Facades\Validator;

final readonly class UpdateProfileData
{
    private function __construct(public string $name) {}

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        if (is_string($input['name'] ?? null)) {
            $input['name'] = trim($input['name']);
        }

        $data = Validator::validate($input, [
            'name' => ['required', 'string', 'max:255'],
        ], [
            'name.required' => 'Informe o nome.',
        ]);

        return new self($data['name']);
    }
}
