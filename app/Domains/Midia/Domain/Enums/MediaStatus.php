<?php

declare(strict_types=1);

namespace App\Domains\Midia\Domain\Enums;

/**
 * Situação de uma mídia. Só existe mídia pronta: o envio é atômico, então não há estado de processamento nem mídia
 * incompleta. `removal_requested` marca a mídia em uso que foi pedida para exclusão e sai quando deixar de ser usada.
 */
enum MediaStatus: string
{
    case Ready = 'ready';
    case RemovalRequested = 'removal_requested';

    public function label(): string
    {
        return match ($this) {
            self::Ready => 'Pronta',
            self::RemovalRequested => 'Exclusão solicitada',
        };
    }
}
