<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Actions\Hours;

use App\Domains\Institucional\Application\Queries\Hours\HourExceptionOverlapQuery;
use App\Domains\Institucional\Domain\Exceptions\InvalidHourException;
use App\Domains\Institucional\Domain\ValueObjects\ProfileOwner;

/**
 * Garante que o período não cruza outra exceção do mesmo dono. Vale só sob o bloqueio do dono, que é o que serializa
 * cadastros concorrentes.
 */
final readonly class AssertHourExceptionPeriodFreeAction
{
    public function __construct(
        private HourExceptionOverlapQuery $overlap,
    ) {}

    /**
     * @throws InvalidHourException
     */
    public function execute(ProfileOwner $owner, string $startsOn, string $endsOn, ?int $ignoreId): void
    {
        if (! $this->overlap->execute($owner, $startsOn, $endsOn, $ignoreId)) {
            return;
        }

        throw InvalidHourException::in(
            'starts_on',
            'Já existe uma exceção neste período. Ajuste as datas ou edite a exceção existente.',
        );
    }
}
