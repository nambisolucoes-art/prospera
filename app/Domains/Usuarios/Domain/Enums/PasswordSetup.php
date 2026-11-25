<?php

declare(strict_types=1);

namespace App\Domains\Usuarios\Domain\Enums;

enum PasswordSetup: string
{
    case Manual = 'manual';
    case Invite = 'invite';
}
