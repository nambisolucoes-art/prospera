<?php

declare(strict_types=1);

namespace App\Domains\Plataforma\Application\Data;

use App\Domains\Plataforma\Domain\Enums\AccountInspectionMode;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class StartAccountInspectionData
{
    private function __construct(public int $userId, public AccountInspectionMode $mode) {}

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $data = Validator::validate($input, [
            'user_id' => ['required', 'integer', 'min:1'],
            'mode' => ['required', Rule::enum(AccountInspectionMode::class)],
        ], [
            'user_id.required' => 'Selecione um usuário da conta.',
            'user_id.integer' => 'Selecione um usuário válido.',
            'user_id.min' => 'Selecione um usuário válido.',
            'mode.required' => 'Selecione o modo de inspeção.',
            'mode.enum' => 'Selecione um modo de inspeção válido.',
        ]);

        return new self((int) $data['user_id'], AccountInspectionMode::from($data['mode']));
    }
}
