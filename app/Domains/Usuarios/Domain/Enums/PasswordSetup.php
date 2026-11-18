<?php

namespace App\Domains\Usuarios\Domain\Enums;

enum PasswordSetup: string
{
    case Manual = 'manual';
    case Invite = 'invite';
}
