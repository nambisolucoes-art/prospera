<?php

namespace App\Domains\Plataforma\Application\Queries;

use App\Domains\Plataforma\Domain\Models\Client;
use Illuminate\Support\Facades\DB;

final class AccountMembershipsQuery
{
    /**
     * Conta do código com o vínculo da identidade, numa consulta. Sem vínculo, a conta não é carregada.
     *
     * @return array{0: Client, 1: int, 2: string}|null
     */
    public function forCode(int $userId, string $code): ?array
    {
        $account = Client::query()
            ->join('account_memberships as m', 'm.account_id', '=', 'clients.id')
            ->where('clients.code', $code)
            ->where('m.user_id', $userId)
            ->first(['clients.*', 'm.id as context_membership_id', 'm.status as context_membership_status']);

        if ($account === null) {
            return null;
        }

        $membershipId = (int) $account->getAttribute('context_membership_id');
        $status = (string) $account->getAttribute('context_membership_status');

        // A conta segue como modelo limpo: as colunas do vínculo não podem ir para um save posterior.
        $account->offsetUnset('context_membership_id');
        $account->offsetUnset('context_membership_status');
        $account->syncOriginal();

        return [$account, $membershipId, $status];
    }

    /**
     * Vínculos da própria identidade, com os cargos de cada conta. Situação: active (pode abrir),
     * suspended (vínculo suspenso) ou inactive (conta inativa). Uma consulta, sem N+1.
     *
     * @return list<array{code: string, name: string, trade_name: string|null, roles: list<string>, status: string}>
     */
    public function forIdentity(int $userId): array
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

    /** Quantidade de contas que a identidade pode abrir, para decidir entre a entrada direta e a escolha. */
    public function selectableCount(int $userId): int
    {
        return DB::table('account_memberships as m')
            ->join('clients as c', 'c.id', '=', 'm.account_id')
            ->where('m.user_id', $userId)
            ->where('m.status', 'active')
            ->where('c.active', true)
            ->count();
    }
}
