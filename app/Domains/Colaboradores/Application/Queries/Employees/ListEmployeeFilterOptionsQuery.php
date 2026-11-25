<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Queries\Employees;

use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use Illuminate\Support\Facades\DB;

/**
 * Opções dos filtros de unidade, departamento e função: só o que as lotações visíveis ao operador usam.
 * Nada de unidade, departamento ou função fora do escopo aparece como opção.
 */
final readonly class ListEmployeeFilterOptionsQuery
{
    /**
     * @return array{
     *     establishments: list<array{code: string, name: string}>,
     *     departments: list<array{key: string, name: string}>,
     *     job_titles: list<array{key: string, name: string}>
     * }
     */
    public function execute(int $accountId, AuthorizedNodes $nodes): array
    {
        $scoped = function () use ($accountId, $nodes) {
            $query = DB::table('employee_assignments as a')->where('a.account_id', $accountId);

            if (! $nodes->tenant) {
                $query->where(fn ($scope) => $scope
                    ->whereIn('a.company_id', $nodes->companies)
                    ->orWhereIn('a.establishment_id', $nodes->establishments));
            }

            return $query;
        };

        $establishments = $scoped()
            ->join('establishments as e', 'e.id', '=', 'a.establishment_id')
            ->distinct()
            ->orderBy('e.name')
            ->get(['e.code', 'e.name']);

        $departments = $scoped()
            ->join('departments as d', 'd.id', '=', 'a.department_id')
            ->groupBy('d.normalized_name')
            ->orderBy('d.normalized_name')
            ->selectRaw('d.normalized_name as item_key, min(d.name) as name')
            ->get();

        $jobTitles = $scoped()
            ->join('job_titles as j', 'j.id', '=', 'a.job_title_id')
            ->groupBy('j.normalized_name')
            ->orderBy('j.normalized_name')
            ->selectRaw('j.normalized_name as item_key, min(j.name) as name')
            ->get();

        return [
            'establishments' => array_values($establishments
                ->map(fn (object $item) => ['code' => (string) $item->code, 'name' => (string) $item->name])->all()),
            'departments' => array_values($departments
                ->map(fn (object $item) => ['key' => (string) $item->item_key, 'name' => (string) $item->name])->all()),
            'job_titles' => array_values($jobTitles
                ->map(fn (object $item) => ['key' => (string) $item->item_key, 'name' => (string) $item->name])->all()),
        ];
    }
}
