<?php

namespace App\Domains\Acessos\Domain\Concerns;

use App\Domains\Acessos\Domain\Models\Role;

/** Nomeia o cargo de `role_id` no histórico de atribuições e disponibilidades, dentro da conta do registro. */
trait NamesAuditedRole
{
    /**
     * @param  list<int>  $ids
     * @return list<string>|null
     */
    public function auditRelationNames(string $field, array $ids): ?array
    {
        if ($field !== 'role_id') {
            return null;
        }

        $names = Role::where('account_id', $this->auditAccountId())
            ->whereIn('id', $ids)
            ->pluck('name', 'id');

        return array_map(fn (int $id): string => $names[$id] ?? 'Cargo #'.$id, $ids);
    }
}
