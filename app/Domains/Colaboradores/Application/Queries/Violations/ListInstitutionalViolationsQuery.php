<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Queries\Violations;

use App\Domains\Institucional\Domain\Enums\ProfileSectionType;
use App\Support\BusinessDate;
use Illuminate\Support\Facades\DB;

/**
 * Invariantes das lotações que dependem de várias linhas e que o banco não garante sozinho (seção 10.3.1).
 * Só lê: nunca corrige. Cada violação é uma frase com os identificadores para investigar.
 */
final readonly class ListInstitutionalViolationsQuery
{
    /** @return list<string> */
    public function execute(): array
    {
        $today = BusinessDate::today();
        $violations = [];

        $overlaps = DB::table('employee_assignments as a')
            ->join('employee_assignments as b', function ($join): void {
                $join->on('a.employee_id', '=', 'b.employee_id')
                    ->on('a.establishment_id', '=', 'b.establishment_id')
                    ->whereColumn('a.id', '<', 'b.id');
            })
            ->whereRaw('b.starts_on <= coalesce(a.ends_on, ?)', ['9999-12-31'])
            ->whereRaw('a.starts_on <= coalesce(b.ends_on, ?)', ['9999-12-31'])
            ->get(['a.id as first', 'b.id as second', 'a.employee_id']);

        foreach ($overlaps as $row) {
            $violations[] = "Lotações {$row->first} e {$row->second} do colaborador {$row->employee_id} se sobrepõem na mesma unidade.";
        }

        $primaries = DB::table('employee_assignments as a')
            ->join('employee_assignments as b', function ($join): void {
                $join->on('a.employee_id', '=', 'b.employee_id')->whereColumn('a.id', '<', 'b.id');
            })
            ->where('a.is_primary', true)
            ->where('b.is_primary', true)
            ->whereRaw('b.starts_on <= coalesce(a.ends_on, ?)', ['9999-12-31'])
            ->whereRaw('a.starts_on <= coalesce(b.ends_on, ?)', ['9999-12-31'])
            ->get(['a.id as first', 'b.id as second', 'a.employee_id']);

        foreach ($primaries as $row) {
            $violations[] = "Lotações principais {$row->first} e {$row->second} do colaborador {$row->employee_id} se sobrepõem.";
        }

        $openPrimaries = DB::table('employee_assignments')
            ->where('is_primary', true)
            ->whereNull('ends_on')
            ->groupBy('employee_id')
            ->havingRaw('count(*) > 1')
            ->pluck('employee_id');

        foreach ($openPrimaries as $employeeId) {
            $violations[] = "Colaborador {$employeeId} tem mais de uma lotação principal sem data final.";
        }

        $reversed = DB::table('employee_assignments')->whereColumn('ends_on', '<', 'starts_on')->pluck('id');

        foreach ($reversed as $id) {
            $violations[] = "Lotação {$id} termina antes de começar.";
        }

        $incoherent = DB::table('employee_assignments')
            ->where(fn ($query) => $query
                ->whereNotIn('contact_mode', ['inherit', 'override'])
                ->orWhere(fn ($mode) => $mode->where('contact_mode', 'inherit')->whereNotNull('work_contacts'))
                ->orWhere(fn ($mode) => $mode->where('contact_mode', 'override')->whereNull('work_contacts')))
            ->pluck('id');

        foreach ($incoherent as $id) {
            $violations[] = "Lotação {$id} tem modo de contato incoerente com os contatos gravados.";
        }

        $crossCompany = DB::table('employee_assignments as a')
            ->join('employees as e', 'e.id', '=', 'a.employee_id')
            ->join('establishments as s', 's.id', '=', 'a.establishment_id')
            ->leftJoin('departments as d', 'd.id', '=', 'a.department_id')
            ->leftJoin('job_titles as j', 'j.id', '=', 'a.job_title_id')
            ->where(fn ($query) => $query
                ->whereColumn('e.company_id', '!=', 'a.company_id')
                ->orWhereColumn('s.company_id', '!=', 'a.company_id')
                ->orWhereColumn('e.account_id', '!=', 'a.account_id')
                ->orWhere(fn ($catalog) => $catalog->whereNotNull('d.id')->whereColumn('d.company_id', '!=', 'a.company_id'))
                ->orWhere(fn ($catalog) => $catalog->whereNotNull('j.id')->whereColumn('j.company_id', '!=', 'a.company_id')))
            ->pluck('a.id');

        foreach ($crossCompany as $id) {
            $violations[] = "Lotação {$id} mistura conta, empresa, unidade, departamento ou função de origens diferentes.";
        }

        $inactive = DB::table('employee_assignments as a')
            ->join('employees as e', 'e.id', '=', 'a.employee_id')
            ->where('e.active', false)
            ->where('a.starts_on', '<=', $today)
            ->where(fn ($query) => $query->whereNull('a.ends_on')->orWhere('a.ends_on', '>', $today))
            ->pluck('a.id');

        foreach ($inactive as $id) {
            $violations[] = "Lotação {$id} segue vigente com o vínculo do colaborador inativo.";
        }

        // Perfil institucional (S06): coerência entre modo e valor, seções conhecidas e escopo da seção.
        $known = ProfileSectionType::values();

        $sections = DB::table('profile_sections')->get(['id', 'section', 'establishment_id', 'mode', 'value', 'published_mode', 'published_value']);

        foreach ($sections as $row) {
            if (! in_array($row->section, $known, true)) {
                $violations[] = "Seção de perfil {$row->id} tem o tipo desconhecido {$row->section}.";
            }

            if ($row->establishment_id === null && $row->section === 'gallery') {
                $violations[] = "Seção de perfil {$row->id}: a galeria existe só em estabelecimento.";
            }

            if ($row->establishment_id === null && $row->mode !== 'override') {
                $violations[] = "Seção de perfil {$row->id}: a empresa só tem modo override.";
            }

            $workCoherent = ($row->mode === 'override') === ($row->value !== null);
            $publishedCoherent = $row->published_mode === null
                ? $row->published_value === null
                : (($row->published_mode === 'override') === ($row->published_value !== null));

            if (! $workCoherent || ! $publishedCoherent) {
                $violations[] = "Seção de perfil {$row->id} tem modo incoerente com o valor gravado.";
            }
        }

        return $violations;
    }
}
