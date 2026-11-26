<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Domain\Enums;

/**
 * Tipos de exceção de horário (seção 09.5). Feriado e fechamento temporário fecham a unidade nas datas; a diferença é
 * o motivo mostrado ao público. Horário especial troca os turnos das datas por turnos próprios.
 */
enum HourExceptionKind: string
{
    case Holiday = 'holiday';
    case TemporaryClosure = 'temporary_closure';
    case SpecialHours = 'special_hours';

    public function label(): string
    {
        return match ($this) {
            self::Holiday => 'Feriado',
            self::TemporaryClosure => 'Fechamento temporário',
            self::SpecialHours => 'Horário especial',
        };
    }

    public function hasShifts(): bool
    {
        return $this === self::SpecialHours;
    }
}
