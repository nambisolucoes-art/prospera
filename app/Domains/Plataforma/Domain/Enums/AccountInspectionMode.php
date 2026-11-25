<?php

declare(strict_types=1);

namespace App\Domains\Plataforma\Domain\Enums;

enum AccountInspectionMode: string
{
    case ReadOnly = 'read-only';
    case Edit = 'edit';

    public function label(): string
    {
        return match ($this) {
            self::ReadOnly => 'Somente leitura',
            self::Edit => 'Leitura e edição',
        };
    }
}
