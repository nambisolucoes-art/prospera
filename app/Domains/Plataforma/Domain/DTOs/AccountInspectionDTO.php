<?php

namespace App\Domains\Plataforma\Domain\DTOs;

use App\Domains\Plataforma\Domain\Enums\AccountInspectionMode;

final readonly class AccountInspectionDTO
{
    public function __construct(
        public int $administratorId,
        public int $accountId,
        public int $userId,
        public AccountInspectionMode $mode,
        public ?int $membershipId = null,
    ) {}
}
