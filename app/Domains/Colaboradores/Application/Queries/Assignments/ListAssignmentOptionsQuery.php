<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Queries\Assignments;

use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use App\Domains\Colaboradores\Application\Data\Assignments\AssignmentOptions;
use App\Support\BusinessDate;
use Illuminate\Support\Facades\DB;

/** Unidades, departamentos e funções que uma lotação da empresa pode escolher, limitados ao escopo de `assign`. */
final readonly class ListAssignmentOptionsQuery
{
    /**
     * @param  list<int>  $keepDepartmentIds
     * @param  list<int>  $keepJobTitleIds
     */
    public function execute(
        int $accountId,
        int $companyId,
        AuthorizedNodes $assign,
        array $keepDepartmentIds = [],
        array $keepJobTitleIds = [],
    ): AssignmentOptions {
        $units = DB::table('establishments')
            ->where('account_id', $accountId)
            ->where('company_id', $companyId)
            ->where('active', true)
            ->when(! $assign->tenant && ! in_array($companyId, $assign->companies, true), fn ($query) => $query
                ->whereIntegerInRaw('id', $assign->establishments))
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'timezone']);

        $departments = DB::table('departments')
            ->where('account_id', $accountId)
            ->where('company_id', $companyId)
            ->where(fn ($query) => $query->where('active', true)->orWhereIn('id', $keepDepartmentIds))
            ->orderBy('name')
            ->get(['id', 'name']);

        $jobTitles = DB::table('job_titles')
            ->where('account_id', $accountId)
            ->where('company_id', $companyId)
            ->where(fn ($query) => $query->where('active', true)->orWhereIn('id', $keepJobTitleIds))
            ->orderBy('name')
            ->get(['id', 'name']);

        return new AssignmentOptions(
            establishmentIds: $units->pluck('id', 'code')->map(fn ($id): int => (int) $id)->all(),
            establishments: array_values($units
                ->map(fn (object $unit) => [
                    'code' => (string) $unit->code,
                    'name' => (string) $unit->name,
                    'today' => BusinessDate::todayIn((string) $unit->timezone),
                ])
                ->all()),
            departments: array_values($departments
                ->map(fn (object $item) => ['id' => (int) $item->id, 'name' => (string) $item->name])
                ->all()),
            jobTitles: array_values($jobTitles
                ->map(fn (object $item) => ['id' => (int) $item->id, 'name' => (string) $item->name])
                ->all()),
        );
    }
}
