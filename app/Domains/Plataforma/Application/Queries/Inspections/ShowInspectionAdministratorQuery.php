<?php

declare(strict_types=1);

namespace App\Domains\Plataforma\Application\Queries\Inspections;

use App\Models\User;

final readonly class ShowInspectionAdministratorQuery
{
    public function execute(int $identityId): ?User
    {
        $user = User::where('is_saas_admin', true)
            ->where('access_enabled', true)
            ->whereNotNull('email_verified_at')
            ->find($identityId);

        return $user?->can('platform.inspection.start') ? $user : null;
    }
}
