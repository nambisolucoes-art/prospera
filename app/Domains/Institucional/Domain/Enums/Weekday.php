<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Domain\Enums;

/** Dias da semana do horário semanal. O valor é a chave gravada; `iso()` é o dia ISO-8601 (segunda é 1). */
enum Weekday: string
{
    case Monday = 'mon';
    case Tuesday = 'tue';
    case Wednesday = 'wed';
    case Thursday = 'thu';
    case Friday = 'fri';
    case Saturday = 'sat';
    case Sunday = 'sun';

    public function iso(): int
    {
        return match ($this) {
            self::Monday => 1,
            self::Tuesday => 2,
            self::Wednesday => 3,
            self::Thursday => 4,
            self::Friday => 5,
            self::Saturday => 6,
            self::Sunday => 7,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Monday => 'Segunda-feira',
            self::Tuesday => 'Terça-feira',
            self::Wednesday => 'Quarta-feira',
            self::Thursday => 'Quinta-feira',
            self::Friday => 'Sexta-feira',
            self::Saturday => 'Sábado',
            self::Sunday => 'Domingo',
        };
    }

    public static function fromIso(int $iso): self
    {
        foreach (self::cases() as $day) {
            if ($day->iso() === $iso) {
                return $day;
            }
        }

        throw new \InvalidArgumentException('Dia ISO inválido: '.$iso);
    }
}
