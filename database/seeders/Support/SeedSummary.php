<?php

namespace Database\Seeders\Support;

use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Resumo dos usuários de teste lido do banco depois da semeadura: mostra o que cada identidade realmente
 * recebeu, sem repetir uma lista fixa que poderia divergir dos seeders.
 */
final class SeedSummary
{
    /** @return list<array{0: string, 1: string}> */
    public static function users(): array
    {
        $assignments = DB::table('users as u')
            ->leftJoin('account_memberships as m', 'm.user_id', '=', 'u.id')
            ->leftJoin('role_assignments as a', 'a.membership_id', '=', 'm.id')
            ->leftJoin('roles as r', 'r.id', '=', 'a.role_id')
            ->leftJoin('companies as c', 'c.id', '=', 'a.company_id')
            ->leftJoin('establishments as e', 'e.id', '=', 'a.establishment_id')
            ->orderBy('u.id')
            ->orderBy('a.id')
            ->get([
                'u.id',
                'u.email',
                'u.is_saas_admin',
                'm.status',
                'a.scope',
                'r.name as role',
                'c.trade_name as company_trade_name',
                'c.name as company_name',
                'e.name as establishment',
            ]);

        $rows = [];

        foreach ($assignments->groupBy('id') as $grants) {
            $first = $grants->first();
            $rows[] = [(string) $first->email, self::profile(array_values($grants->all()))];
        }

        return $rows;
    }

    /** @param list<stdClass> $grants */
    private static function profile(array $grants): string
    {
        $first = $grants[0];

        if ((bool) $first->is_saas_admin) {
            return 'Administrador da plataforma SaaS';
        }

        $places = [];

        foreach ($grants as $grant) {
            if ($grant->role === null) {
                continue;
            }

            $place = match ($grant->scope) {
                'company' => $grant->company_trade_name ?? $grant->company_name,
                'establishment' => $grant->establishment,
                default => 'toda a conta',
            };

            $places[] = $grant->role.' em '.$place;
        }

        $profile = $places === [] ? 'Sem atribuições' : implode('; ', $places);

        return $first->status === 'suspended' ? $profile.' (vínculo suspenso)' : $profile;
    }
}
