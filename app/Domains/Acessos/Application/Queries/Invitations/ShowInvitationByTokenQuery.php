<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Queries\Invitations;

use App\Domains\Acessos\Domain\Models\AccountInvitation;

final readonly class ShowInvitationByTokenQuery
{
    public function execute(string $token): ?AccountInvitation
    {
        return AccountInvitation::query()
            ->where('token_hash', AccountInvitation::hashToken($token))
            ->with(['account:id,name,active', 'role:id,name'])
            ->first();
    }
}
