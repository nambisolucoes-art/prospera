<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Queries\Memberships;

use Illuminate\Support\Facades\DB;

final readonly class ListIdentitySelfSlugsQuery
{
    /**
     * Permissões self dos cargos atribuídos nos vínculos ativos de contas ativas. Retorna null quando a
     * identidade não tem nenhum vínculo, em qualquer situação.
     *
     * @return list<string>|null
     */
    public function execute(int $userId): ?array
    {
        $memberships = DB::table('account_memberships')->where('user_id', $userId);

        if (! $memberships->exists()) {
            return null;
        }

        $slugs = DB::table('account_memberships as m')
            ->join('clients as c', 'c.id', '=', 'm.account_id')
            ->join('role_assignments as a', 'a.membership_id', '=', 'm.id')
            ->join('permission_role as pr', 'pr.role_id', '=', 'a.role_id')
            ->join('permissions as p', 'p.id', '=', 'pr.permission_id')
            ->where('m.user_id', $userId)
            ->where('m.status', 'active')
            ->where('c.active', true)
            ->where('p.is_system', true)
            ->where('p.scope', 'self')
            ->distinct()
            ->orderBy('p.slug')
            ->pluck('p.slug')
            ->map(fn (mixed $slug): string => (string) $slug)
            ->all();

        return array_values($slugs);
    }
}
