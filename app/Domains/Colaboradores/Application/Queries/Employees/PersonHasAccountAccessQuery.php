<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Queries\Employees;

use Illuminate\Support\Facades\DB;

/** Informa se a pessoa tem acesso ativo à conta, pelo vínculo de acesso (`account_memberships.person_id`). */
final readonly class PersonHasAccountAccessQuery
{
    public function execute(int $accountId, int $personId): bool
    {
        return DB::table('account_memberships')
            ->where('account_id', $accountId)
            ->where('person_id', $personId)
            ->where('status', 'active')
            ->exists();
    }
}
