<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Application\Queries;

use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Colaboradores\Application\Queries\Employees\EmployeeScope;
use App\Domains\Colaboradores\Domain\Models\Employee;
use App\Domains\Pessoas\Domain\Enums\DerivedRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Etiquetas especiais derivadas das relações ativas das pessoas, calculadas para quem está vendo.
 *
 * Cada papel só aparece se houver relação ativa numa empresa em que o leitor tem a permissão de visualizar.
 * Uma relação em empresa fora do acesso nunca produz etiqueta.
 */
final readonly class ListDerivedPersonTagsQuery
{
    public function __construct(private EffectivePermissions $permissions) {}

    /**
     * @param  list<int>  $personIds
     * @return array<int, list<array{id: int, name: string, color: string}>>
     */
    public function execute(int $accountId, array $personIds, User $viewer): array
    {
        if ($personIds === []) {
            return [];
        }

        $definitions = DB::table('tags')
            ->where('account_id', $accountId)
            ->where('module', 'people')
            ->whereIn('system_key', array_map(fn (DerivedRole $role) => $role->value, DerivedRole::cases()))
            ->get(['id', 'name', 'color', 'system_key'])
            ->keyBy('system_key');

        $result = [];

        foreach (DerivedRole::cases() as $role) {
            $nodes = $role->viewNodes($this->permissions, $viewer);

            if ($nodes->isEmpty()) {
                continue;
            }

            if ($role === DerivedRole::Employee) {
                $query = Employee::query()
                    ->where('employees.account_id', $accountId)
                    ->where('employees.active', true)
                    ->whereIntegerInRaw('employees.person_id', $personIds);

                EmployeeScope::apply($query, $nodes);

                $related = $query->distinct()->pluck('employees.person_id');
            } else {
                $related = DB::table($role->table())
                    ->where('account_id', $accountId)
                    ->where('active', true)
                    ->whereIntegerInRaw('person_id', $personIds)
                    ->when(! $nodes->tenant, fn ($query) => $query->whereIntegerInRaw('company_id', $nodes->companies))
                    ->distinct()
                    ->pluck('person_id');
            }

            $definition = $definitions->get($role->value);

            foreach ($related as $personId) {
                $result[(int) $personId][] = [
                    'id' => (int) ($definition->id ?? 0),
                    'name' => (string) ($definition->name ?? $role->label()),
                    'color' => (string) ($definition->color ?? $role->color()),
                ];
            }
        }

        return $result;
    }
}
