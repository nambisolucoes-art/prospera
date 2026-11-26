<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Domain\ValueObjects\Hours;

use App\Domains\Institucional\Domain\Exceptions\InvalidHourException;

/**
 * Os turnos de um dia, em ordem e sem sobreposição, no máximo {@see self::MAX} por dia. Dois turnos que se encostam
 * (um termina quando o outro começa) são válidos. `[]` é o vazio intencional.
 */
final readonly class Shifts
{
    public const MAX = 6;

    /** @param list<Shift> $items */
    private function __construct(
        private array $items,
    ) {}

    public static function none(): self
    {
        return new self([]);
    }

    /**
     * Erros saem com a chave relativa à lista (`1.start`), para o chamador prefixar com o campo.
     *
     * @param  list<array{start: string, end: string}>  $items
     *
     * @throws InvalidHourException
     */
    public static function from(array $items): self
    {
        if (count($items) > self::MAX) {
            throw InvalidHourException::in('shifts', 'Informe até '.self::MAX.' turnos por dia.');
        }

        $shifts = [];

        foreach ($items as $index => $item) {
            try {
                $shift = Shift::between($item['start'], $item['end']);
            } catch (InvalidHourException $failure) {
                throw $failure->prefixed((string) $index);
            }

            $previous = $shifts === [] ? null : $shifts[array_key_last($shifts)];

            if ($previous !== null && $shift->start < $previous->end) {
                throw InvalidHourException::in(
                    $index.'.start',
                    'Os turnos devem estar em ordem e sem sobreposição.',
                );
            }

            $shifts[] = $shift;
        }

        return new self($shifts);
    }

    /**
     * Turnos já gravados e validados.
     *
     * @param  list<array{start: string, end: string}>  $stored
     */
    public static function restore(array $stored): self
    {
        return new self(array_map(fn (array $shift): Shift => Shift::restore($shift), $stored));
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function contains(int $minute): bool
    {
        foreach ($this->items as $shift) {
            if ($shift->contains($minute)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{start: string, end: string}> */
    public function toArray(): array
    {
        return array_map(fn (Shift $shift): array => $shift->toArray(), $this->items);
    }
}
