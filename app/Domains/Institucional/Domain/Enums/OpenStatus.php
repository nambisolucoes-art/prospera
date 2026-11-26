<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Domain\Enums;

/** Condição de funcionamento de uma unidade num instante. Nunca é gravada: é calculada a cada leitura. */
enum OpenStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case ByAppointment = 'by_appointment';
    /** Nenhum horário foi definido nem pela unidade nem pela empresa. */
    case NotInformed = 'not_informed';
    /** A unidade desativou a seção de horários. */
    case Disabled = 'disabled';
    /** A empresa ou a unidade está inativa. */
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Aberto agora',
            self::Closed => 'Fechado agora',
            self::ByAppointment => 'Atendimento sob agendamento',
            self::NotInformed => 'Horário não informado',
            self::Disabled => 'Horários não exibidos',
            self::Inactive => 'Unidade inativa',
        };
    }
}
