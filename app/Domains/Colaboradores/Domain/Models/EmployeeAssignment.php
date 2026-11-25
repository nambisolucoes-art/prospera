<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Domain\Models;

use App\Domains\Auditoria\Domain\Concerns\AuditsChanges;
use App\Domains\Auditoria\Domain\Contracts\Auditable;
use App\Domains\Colaboradores\Domain\DTOs\Assignments\CreateEmployeeAssignmentDTO;
use App\Domains\Colaboradores\Domain\DTOs\Assignments\UpdateEmployeeAssignmentDTO;
use App\Domains\Organizacao\Domain\Models\Establishment;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Lotação (S05): período em que o colaborador atua num estabelecimento, com função e departamento do período.
 * `starts_on` e `ends_on` são datas de calendário inclusivas (`Y-m-d`), sem hora. Não guarda dado da Pessoa.
 *
 * @property int $id
 * @property int $account_id
 * @property int $company_id
 * @property int $employee_id
 * @property int $establishment_id
 * @property int|null $department_id
 * @property int|null $job_title_id
 * @property string $starts_on
 * @property string|null $ends_on
 * @property bool $is_primary
 */
#[Fillable([
    'account_id', 'company_id', 'employee_id', 'establishment_id', 'department_id', 'job_title_id',
    'starts_on', 'ends_on', 'is_primary',
])]
class EmployeeAssignment extends Model implements Auditable
{
    use AuditsChanges;

    public const CURRENT = 'current';

    public const FUTURE = 'future';

    public const ENDED = 'ended';

    protected $table = 'employee_assignments';

    protected $attributes = ['is_primary' => false];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public static function register(
        CreateEmployeeAssignmentDTO $data,
        int $accountId,
        int $companyId,
        int $employeeId,
    ): self {
        return new self([
            'account_id' => $accountId,
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'establishment_id' => $data->establishmentId,
            'department_id' => $data->departmentId,
            'job_title_id' => $data->jobTitleId,
            'starts_on' => $data->startsOn,
            'ends_on' => $data->endsOn,
            'is_primary' => $data->isPrimary,
        ]);
    }

    public function revise(UpdateEmployeeAssignmentDTO $data): void
    {
        $this->fill([
            'department_id' => $data->departmentId,
            'job_title_id' => $data->jobTitleId,
            'starts_on' => $data->startsOn,
            'ends_on' => $data->endsOn,
        ]);
    }

    /** Vigente, futura ou encerrada na data de calendário informada. */
    public function statusOn(string $today): string
    {
        $starts = substr((string) $this->starts_on, 0, 10);
        $ends = $this->ends_on === null ? null : substr((string) $this->ends_on, 0, 10);

        if ($starts > $today) {
            return self::FUTURE;
        }

        return $ends !== null && $ends < $today ? self::ENDED : self::CURRENT;
    }

    // Nome do histórico: colaborador, empresa e unidade, lidos dentro da mesma conta.
    public function getNameAttribute(): ?string
    {
        $row = DB::table('employees')
            ->join('people', fn ($join) => $join->on('people.id', '=', 'employees.person_id')
                ->on('people.account_id', '=', 'employees.account_id'))
            ->join('establishments', fn ($join) => $join->on('establishments.company_id', '=', 'employees.company_id'))
            ->where('employees.account_id', $this->account_id)
            ->where('employees.id', $this->employee_id)
            ->where('establishments.id', $this->establishment_id)
            ->first(['people.name', 'establishments.name as establishment_name']);

        return $row === null ? null : $row->name.' em '.$row->establishment_name;
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Establishment, $this> */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public static function auditType(): string
    {
        return 'employee-assignment';
    }

    /** @return array<string,string> */
    public static function auditedFields(): array
    {
        return [
            'account_id' => 'Conta',
            'company_id' => 'Empresa',
            'employee_id' => 'Colaborador',
            'establishment_id' => 'Estabelecimento',
            'department_id' => 'Departamento',
            'job_title_id' => 'Função',
            'starts_on' => 'Início',
            'ends_on' => 'Fim',
            'is_primary' => 'Lotação principal',
        ];
    }
}
