<?php

namespace App\Domains\Plataforma\Infrastructure\Session;

use App\Domains\Plataforma\Domain\DTOs\AccountInspectionDTO;
use App\Domains\Plataforma\Domain\Enums\AccountInspectionMode;
use Illuminate\Contracts\Session\Session;

final class AccountInspectionSession
{
    private const KEY = 'account_inspection';

    public static function current(Session $session): ?AccountInspectionDTO
    {
        $values = $session->get(self::KEY);

        return $values === null ? null : new AccountInspectionDTO(
            $values['administrator_id'], $values['account_id'], $values['user_id'],
            AccountInspectionMode::tryFrom($values['mode'] ?? '') ?? AccountInspectionMode::ReadOnly,
            $values['membership_id'] ?? null,
            $values['id'] ?? null,
        );
    }

    public static function start(Session $session, AccountInspectionDTO $inspection): void
    {
        $session->put(self::KEY, self::context($inspection));
    }

    /**
     * @return array{
     *     id: string|null,
     *     administrator_id: int,
     *     account_id: int,
     *     user_id: int,
     *     mode: string,
     *     membership_id: int|null,
     * }
     */
    public static function context(AccountInspectionDTO $inspection): array
    {
        return [
            'id' => $inspection->id,
            'administrator_id' => $inspection->administratorId,
            'account_id' => $inspection->accountId,
            'user_id' => $inspection->userId,
            'membership_id' => $inspection->membershipId,
            'mode' => $inspection->mode->value,
        ];
    }
}
