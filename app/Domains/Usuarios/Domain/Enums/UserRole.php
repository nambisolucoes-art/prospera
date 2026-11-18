<?php

namespace App\Domains\Usuarios\Domain\Enums;

enum UserRole: string
{
    case PlatformAdministrator = 'platform-admin';
    case AccountAdministrator = 'account-admin';
    case AccountMember = 'account-member';

    public function requiresAccount(): bool
    {
        return $this !== self::PlatformAdministrator;
    }
}
