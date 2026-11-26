<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Actions\Hours;

use App\Domains\Institucional\Application\Queries\Hours\HourExceptionImpactQuery;
use App\Domains\Institucional\Domain\Enums\HourExceptionKind;
use App\Domains\Institucional\Domain\Exceptions\InvalidHourException;
use App\Domains\Institucional\Domain\ValueObjects\ProfileOwner;

/**
 * Exceção da empresa que alcança unidades com horário próprio (`applies_to_overrides`) exige o resumo confirmado. O
 * alcance é recalculado no banco, sob o bloqueio da empresa, e o `token` confirmado precisa coincidir: se as unidades
 * mudaram desde que o resumo foi aberto, a gravação é recusada e o resumo precisa ser refeito.
 */
final readonly class AssertHourExceptionImpactAction
{
    public function __construct(
        private HourExceptionImpactQuery $impact,
    ) {}

    /**
     * @throws InvalidHourException
     */
    public function execute(
        ProfileOwner $owner,
        HourExceptionKind $kind,
        string $startsOn,
        string $endsOn,
        bool $appliesToOverrides,
        ?string $confirmedToken,
    ): void {
        if (! $appliesToOverrides) {
            return;
        }

        $currentImpact = $this->impact->execute($owner, $kind, $startsOn, $endsOn);

        if ($confirmedToken === $currentImpact->token) {
            return;
        }

        throw InvalidHourException::in(
            'impact',
            'O alcance mudou desde que você abriu o resumo. Revise as unidades e confirme de novo.',
        );
    }
}
