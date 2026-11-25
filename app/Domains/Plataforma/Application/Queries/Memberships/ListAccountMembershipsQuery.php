<?php

declare(strict_types=1);

namespace App\Domains\Plataforma\Application\Queries\Memberships;

use Illuminate\Support\Facades\DB;

final readonly class ListAccountMembershipsQuery
{
    /**
     * Vínculos da própria identidade, com os cargos de cada conta. Situação: active (pode abrir),
     * suspended (vínculo suspenso) ou inactive (conta inativa). Uma consulta, sem N+1.
     *
     * @return list<array{code: string, name: string, trade_name: string|null, roles: list<string>, status: string}>
     */
    public function execute(int $userId): array
    {
        $aggregate = DB::getDriverName() === 'pgsql'
            ? 'json_agg(distinct r.name order by r.name)'
            : 'json_group_array(distinct r.name)';
        $roles = DB::table('role_assignments as a')
            ->join('roles as r', 'r.id', '=', 'a.role_id')
            ->whereColumn('a.membership_id', 'm.id')
            ->selectRaw($aggregate);

        $accounts = DB::table('account_memberships as m')
            ->join('clients as c', 'c.id', '=', 'm.account_id')
            ->where('m.user_id', $userId)
            ->orderByRaw("case when m.status = 'active' and c.active then 0 else 1 end")
            ->orderByRaw('m.last_used_at is null')
            ->orderByDesc('m.last_used_at')
            ->orderBy('c.name')
            ->orderBy('c.id')
            ->select(['c.code', 'c.name', 'c.details', 'c.active', 'm.status'])
            ->selectSub($roles, 'role_names')
            ->get();

        if ($accounts->isEmpty()) {
            return [];
        }

        $rows = $accounts->map(function (object $account): array {
            $details = json_decode((string) $account->details, true);
            $names = json_decode((string) $account->role_names, true) ?? [];
            sort($names);
            $status = match (true) {
                $account->status !== 'active' => 'suspended',
                ! (bool) $account->active => 'inactive',
                default => 'active',
            };

            return [
                'code' => (string) $account->code,
                'name' => (string) $account->name,
                'trade_name' => is_array($details) && filled($details['trade_name'] ?? null)
                    ? (string) $details['trade_name']
                    : null,
                // Cargos só aparecem para quem pode abrir a conta.
                'roles' => $status === 'active' ? $names : [],
                'status' => $status,
            ];
        })->all();

        return array_values($rows);
    }
}
