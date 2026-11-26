<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Domain\ValueObjects\Hours;

use App\Domains\Institucional\Domain\Enums\OpenStatus;
use Carbon\CarbonImmutable;

/**
 * O horário efetivo de uma unidade num instante, calculado (nunca gravado). É o contrato que a S11, a S12 e a S13 leem.
 *
 * - `unitCode`: o código da unidade na URL, para a tela casar o resultado com a lista de unidades.
 * - `status` e `closedReason`: a condição de funcionamento e, quando fechada, o motivo (`closed_day`,
 *   `outside_shifts`, `holiday` ou `temporary_closure`).
 * - `schedule` e `scheduleSource` (`weekly` ou `exception`): o atendimento do dia local, já com a exceção aplicada.
 * - `hoursSource` e `hoursMode`: de onde vem o horário semanal (`establishment`, `company` ou `platform`) e o modo da unidade.
 * - `timezone`, `localDate` e `localTime`: o fuso IANA da unidade e a data e a hora locais do instante.
 * - `exception`: a exceção aplicada ao dia, com o dono (`company` ou `establishment`) e se alcançou um horário próprio.
 * - `validUntil`: o início do próximo dia civil local, o limite de validade de tudo que depende da data (seção 09.7).
 */
final readonly class EffectiveHours
{
    /**
     * @param  array<string, mixed>|null  $exception  `id`, `kind`, `owner`, `starts_on`, `ends_on`, `note` e `reached_override`
     */
    public function __construct(
        public string $unitCode,
        public OpenStatus $status,
        public ?string $closedReason,
        public ?DaySchedule $schedule,
        public ?string $scheduleSource,
        public ?string $hoursSource,
        public ?string $hoursMode,
        public string $timezone,
        public string $localDate,
        public string $localTime,
        public ?array $exception,
        public CarbonImmutable $validUntil,
    ) {}

    public function isOpen(): bool
    {
        return $this->status === OpenStatus::Open;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'unit_code' => $this->unitCode,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'closed_reason' => $this->closedReason,
            'schedule' => $this->schedule?->toArray(),
            'schedule_source' => $this->scheduleSource,
            'hours_source' => $this->hoursSource,
            'hours_mode' => $this->hoursMode,
            'timezone' => $this->timezone,
            'local_date' => $this->localDate,
            'local_time' => $this->localTime,
            'exception' => $this->exception,
            'valid_until' => $this->validUntil->utc()->toIso8601String(),
        ];
    }
}
