<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Domain\Enums;

/**
 * Como a unidade atende num dia da semana.
 * `open`: atende nos turnos (ao menos um). `appointment`: só sob agendamento, com janelas opcionais. `closed`: sem
 * atendimento e sem turnos.
 */
enum DayKind: string
{
    case Open = 'open';
    case Appointment = 'appointment';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Aberto',
            self::Appointment => 'Somente com agendamento',
            self::Closed => 'Fechado',
        };
    }
}
