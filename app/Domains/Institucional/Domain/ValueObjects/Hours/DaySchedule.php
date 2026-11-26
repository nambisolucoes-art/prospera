<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Domain\ValueObjects\Hours;

use App\Domains\Institucional\Domain\Enums\DayKind;
use App\Domains\Institucional\Domain\Enums\OpenStatus;
use App\Domains\Institucional\Domain\Exceptions\InvalidHourException;

/** O atendimento de um dia: o tipo do dia e os turnos. Aberto exige turnos e fechado não os tem. */
final readonly class DaySchedule
{
    private function __construct(
        public DayKind $kind,
        public Shifts $shifts,
    ) {}

    /**
     * @throws InvalidHourException
     */
    public static function of(DayKind $kind, Shifts $shifts): self
    {
        if ($kind === DayKind::Closed && ! $shifts->isEmpty()) {
            throw InvalidHourException::in('shifts', 'Um dia fechado não tem turnos.');
        }

        if ($kind === DayKind::Open && $shifts->isEmpty()) {
            throw InvalidHourException::in('shifts', 'Informe ao menos um turno ou marque o dia como fechado.');
        }

        return new self($kind, $shifts);
    }

    public static function closed(): self
    {
        return new self(DayKind::Closed, Shifts::none());
    }

    /**
     * Dia já gravado e validado.
     *
     * @param  array<string, mixed>  $stored
     */
    public static function restore(array $stored): self
    {
        $kind = DayKind::tryFrom((string) ($stored['kind'] ?? '')) ?? DayKind::Closed;

        return new self($kind, Shifts::restore($stored['shifts'] ?? []));
    }

    /** Aberto ou sob agendamento só dentro de algum turno; um dia sob agendamento sem turnos vale o dia todo. */
    public function statusAt(int $minute): OpenStatus
    {
        if ($this->kind === DayKind::Closed) {
            return OpenStatus::Closed;
        }

        if (! $this->shifts->isEmpty() && ! $this->shifts->contains($minute)) {
            return OpenStatus::Closed;
        }

        return $this->kind === DayKind::Appointment ? OpenStatus::ByAppointment : OpenStatus::Open;
    }

    /** @return array{kind: string, shifts: list<array{start: string, end: string}>} */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind->value,
            'shifts' => $this->shifts->toArray(),
        ];
    }
}
