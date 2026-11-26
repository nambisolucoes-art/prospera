<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Queries\Violations;

use App\Domains\Colaboradores\Application\Queries\Assignments\LocalDatePredicate;
use App\Domains\Institucional\Application\Data\Profile\Sections\HoursWeeklyValue;
use App\Domains\Institucional\Domain\Enums\ProfileSectionType;
use App\Domains\Institucional\Domain\Exceptions\InvalidHourException;
use App\Domains\Institucional\Domain\ValueObjects\Hours\Shifts;
use App\Domains\Organizacao\Application\Queries\Establishments\ListTimezoneDatesQuery;
use App\Support\IanaTimezone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Invariantes das lotações que dependem de várias linhas e que o banco não garante sozinho (seção 10.3.1).
 * Só lê: nunca corrige. Cada violação é uma frase com os identificadores para investigar.
 */
final readonly class ListInstitutionalViolationsQuery
{
    public function __construct(private ListTimezoneDatesQuery $timezoneDates) {}

    /** @return list<string> */
    public function execute(): array
    {
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
            ->where('e.active', false);

        // "Vigente" é a data civil do fuso da unidade de cada lotação (PD2-08).
        LocalDatePredicate::apply($inactive, LocalDatePredicate::OPEN, $this->timezoneDates->execute(), 'a.');
        $inactive = $inactive->pluck('a.id');

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

        // Horários (S07): valor semanal válido nos dois pares, fuso IANA e exceções coerentes e sem cruzamento por dono.
        foreach ($sections as $row) {
            if ($row->section !== ProfileSectionType::HoursWeekly->value) {
                continue;
            }

            foreach (['value', 'published_value'] as $column) {
                if ($row->{$column} === null) {
                    continue;
                }

                try {
                    HoursWeeklyValue::fromInput(json_decode((string) $row->{$column}, true) ?: [], []);
                } catch (ValidationException) {
                    $violations[] = "Seção de perfil {$row->id} tem horário semanal inválido ({$column}).";
                }
            }
        }

        $zones = DB::table('establishments')->select('id', 'timezone')->get();

        foreach ($zones as $unit) {
            if (! IanaTimezone::isValid((string) $unit->timezone)) {
                $violations[] = "Estabelecimento {$unit->id} tem o fuso {$unit->timezone}, que não é um identificador IANA.";
            }
        }

        $exceptions = DB::table('hour_exceptions')->orderBy('company_id')->orderBy('establishment_id')->orderBy('starts_on')->orderBy('id')
            ->get(['id', 'company_id', 'establishment_id', 'kind', 'starts_on', 'ends_on', 'shifts', 'applies_to_overrides']);

        $previous = null;

        foreach ($exceptions as $exception) {
            if ($exception->kind === 'special_hours') {
                try {
                    Shifts::from(json_decode((string) $exception->shifts, true) ?: []);
                } catch (InvalidHourException) {
                    $violations[] = "Exceção de horário {$exception->id} tem turnos inválidos.";
                }
            }

            if ($previous !== null
                && $previous->company_id === $exception->company_id
                && $previous->establishment_id === $exception->establishment_id
                && substr((string) $previous->ends_on, 0, 10) >= substr((string) $exception->starts_on, 0, 10)) {
                $violations[] = "Exceções de horário {$previous->id} e {$exception->id} do mesmo dono se sobrepõem.";
            }

            $previous = $exception;
        }

        return $violations;
    }
}
