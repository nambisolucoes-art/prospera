<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Domain\ValueObjects\Hours;

use App\Domains\Institucional\Domain\Exceptions\InvalidHourException;

/**
 * Um turno de atendimento dentro de um único dia civil, em minutos desde a meia-noite local: `[início, fim)`.
 *
 * O turno não atravessa a meia-noite (o plano não define esse caso): atender depois dela é um turno até 24:00 num dia e
 * outro a partir de 00:00 no dia seguinte. O fim 24:00 (1440) significa "até o fim do dia". No início do turno a
 * unidade já está aberta e no fim já está fechada.
 */
final readonly class Shift
{
    public const END_OF_DAY = 1440;

    private function __construct(
        public int $start,
        public int $end,
    ) {}

    /**
     * @throws InvalidHourException
     */
    public static function between(string $start, string $end): self
    {
        $startMinutes = self::minutes($start, false);
        $endMinutes = self::minutes($end, true);

        if ($startMinutes === null) {
            throw InvalidHourException::in('start', 'Informe a hora de início no formato HH:MM, de 00:00 a 23:59.');
        }

        if ($endMinutes === null) {
            throw InvalidHourException::in('end', 'Informe a hora de término no formato HH:MM, até 24:00.');
        }

        if ($endMinutes <= $startMinutes) {
            throw InvalidHourException::in(
                'end',
                'O término deve ser depois do início. Um turno não atravessa a meia-noite: '
                .'use um turno até 24:00 e outro a partir de 00:00 no dia seguinte.',
            );
        }

        return new self($startMinutes, $endMinutes);
    }

    /**
     * Turno já gravado e validado.
     *
     * @param  array{start: string, end: string}  $stored
     */
    public static function restore(array $stored): self
    {
        return new self(
            start: self::minutes($stored['start'], false) ?? 0,
            end: self::minutes($stored['end'], true) ?? self::END_OF_DAY,
        );
    }

    public function contains(int $minute): bool
    {
        return $minute >= $this->start && $minute < $this->end;
    }

    /** @return array{start: string, end: string} */
    public function toArray(): array
    {
        return [
            'start' => self::format($this->start),
            'end' => self::format($this->end),
        ];
    }

    private static function minutes(string $time, bool $allowEndOfDay): ?int
    {
        if (preg_match('/^(\d{2}):(\d{2})$/', $time, $parts) !== 1) {
            return null;
        }

        $hours = (int) $parts[1];
        $minutes = (int) $parts[2];

        if ($minutes > 59 || $hours > 24 || ($hours === 24 && ($minutes !== 0 || ! $allowEndOfDay))) {
            return null;
        }

        return $hours * 60 + $minutes;
    }

    private static function format(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
