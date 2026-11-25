<?php

declare(strict_types=1);

namespace App\Domains\Plataforma\Domain\ValueObjects;

use App\Domains\Plataforma\Domain\Enums\AccountInspectionMode;

final readonly class AccountInspection
{
    public function __construct(
        public int $administratorId,
        public int $accountId,
        public int $userId,
        public AccountInspectionMode $mode,
        public ?int $membershipId = null,
        public ?string $id = null,
    ) {}
}
