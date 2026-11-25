<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Queries\Memberships;

use App\Domains\Acessos\Domain\Models\RoleAssignment;

final readonly class ShowAssignmentQuery
{
    /** @return array<string,mixed> */
    public function execute(RoleAssignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'role' => $assignment->role->name,
            'scope' => $assignment->scope,
            'coverage' => match ($assignment->scope) {
                'tenant' => 'Empresas e estabelecimentos da conta',
                'company' => 'Empresa e seus estabelecimentos',
                default => 'Somente este estabelecimento',
            },
            'location' => match ($assignment->scope) {
                'company' => $assignment->company?->name,
                'establishment' => $assignment->establishment?->name,
                default => 'Toda a conta',
            },
        ];
    }
}
