<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Domain\DTOs\Hours;

use App\Domains\Institucional\Domain\Enums\HourExceptionKind;

final readonly class UpdateHourExceptionDTO
{
    /** @param list<array{start: string, end: string}>|null $shifts */
    public function __construct(
        public HourExceptionKind $kind,
        public string $startsOn,
        public string $endsOn,
        public ?array $shifts = null,
        public ?string $note = null,
        public bool $appliesToOverrides = false,
    ) {}
}
