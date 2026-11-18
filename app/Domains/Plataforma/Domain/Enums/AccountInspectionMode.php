<?php

namespace App\Domains\Plataforma\Domain\Enums;

enum AccountInspectionMode: string
{
    case ReadOnly = 'read-only';
    case Edit = 'edit';
}
