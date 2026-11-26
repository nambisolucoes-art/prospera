<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Domain\Models;

use App\Domains\Auditoria\Domain\Concerns\AuditsChanges;
use App\Domains\Auditoria\Domain\Contracts\Auditable;
use App\Domains\Institucional\Domain\DTOs\Hours\CreateHourExceptionDTO;
use App\Domains\Institucional\Domain\DTOs\Hours\UpdateHourExceptionDTO;
use App\Domains\Institucional\Domain\Enums\HourExceptionKind;
use App\Domains\Institucional\Domain\Exceptions\InvalidHourException;
use App\Domains\Institucional\Domain\ValueObjects\Hours\Shifts;
use App\Domains\Institucional\Domain\ValueObjects\ProfileOwner;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Exceção de horário de uma empresa ou de um estabelecimento (S07): feriado, fechamento temporário ou horário especial
 * por data ou período. As datas são de calendário (`Y-m-d`), sem hora e sem fuso: o dia vale no fuso da unidade
 * consultada. `applies_to_overrides` só existe na exceção da empresa e a faz alcançar também as unidades com horário
 * próprio (PD2-08). É dado operacional: vale ao salvar, sem rascunho.
 *
 * @property int $id
 * @property int $account_id
 * @property int $company_id
 * @property int|null $establishment_id
 * @property string $kind
 * @property string $starts_on
 * @property string $ends_on
 * @property list<array{start: string, end: string}>|null $shifts
 * @property string|null $note
 * @property bool $applies_to_overrides
 */
#[Fillable([
    'account_id', 'company_id', 'establishment_id', 'kind', 'starts_on', 'ends_on', 'shifts', 'note',
    'applies_to_overrides',
])]
class HourException extends Model implements Auditable
{
    use AuditsChanges;

    /** Período máximo de uma exceção, em dias: validação de sanidade, não regra de negócio. */
    public const MAX_DAYS = 366;

    protected $table = 'hour_exceptions';

    protected $attributes = ['applies_to_overrides' => false];

    protected function casts(): array
    {
        return [
            'shifts' => 'array',
            'applies_to_overrides' => 'boolean',
        ];
    }

    /**
     * @throws InvalidHourException
     */
    public static function register(
        CreateHourExceptionDTO $data,
        int $accountId,
        int $companyId,
        ?int $establishmentId,
    ): self {
        return new self([
            'account_id' => $accountId,
            'company_id' => $companyId,
            'establishment_id' => $establishmentId,
            ...self::attributesFrom($data, $establishmentId),
        ]);
    }

    /**
     * @throws InvalidHourException
     */
    public function revise(UpdateHourExceptionDTO $data): void
    {
        $this->fill(self::attributesFrom($data, $this->establishment_id));
    }

    /** O dono da exceção: a empresa, ou o estabelecimento quando há `establishment_id`. */
    public function owner(): ProfileOwner
    {
        if ($this->establishment_id === null) {
            return ProfileOwner::company($this->account_id, $this->company_id);
        }

        return ProfileOwner::establishment($this->account_id, $this->company_id, $this->establishment_id);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidHourException
     */
    private static function attributesFrom(
        CreateHourExceptionDTO|UpdateHourExceptionDTO $data,
        ?int $establishmentId,
    ): array {
        self::ensureValidPeriod($data->startsOn, $data->endsOn);

        if ($data->appliesToOverrides && $establishmentId !== null) {
            throw InvalidHourException::in(
                'applies_to_overrides',
                'Só a exceção da empresa pode alcançar unidades com horário próprio.',
            );
        }

        return [
            'kind' => $data->kind->value,
            'starts_on' => $data->startsOn,
            'ends_on' => $data->endsOn,
            'shifts' => self::shiftsFrom($data),
            'note' => self::normalizeNullableText($data->note),
            'applies_to_overrides' => $data->appliesToOverrides,
        ];
    }

    /**
     * @throws InvalidHourException
     */
    private static function ensureValidPeriod(string $startsOn, string $endsOn): void
    {
        if ($endsOn < $startsOn) {
            throw InvalidHourException::in('ends_on', 'O último dia não pode ser antes do primeiro dia.');
        }

        $days = CarbonImmutable::createFromFormat('!Y-m-d', $startsOn)
            ->diffInDays(CarbonImmutable::createFromFormat('!Y-m-d', $endsOn)) + 1;

        if ($days > self::MAX_DAYS) {
            throw InvalidHourException::in('ends_on', 'O período deve ter no máximo '.self::MAX_DAYS.' dias.');
        }
    }

    /**
     * Só o horário especial tem turnos, e ele exige ao menos um.
     *
     * @return list<array{start: string, end: string}>|null
     *
     * @throws InvalidHourException
     */
    private static function shiftsFrom(CreateHourExceptionDTO|UpdateHourExceptionDTO $data): ?array
    {
        $given = $data->shifts ?? [];

        if (! $data->kind->hasShifts()) {
            if ($given !== []) {
                throw InvalidHourException::in('shifts', 'Só o horário especial tem turnos.');
            }

            return null;
        }

        try {
            $shifts = Shifts::from($given);
        } catch (InvalidHourException $failure) {
            throw $failure->prefixed('shifts');
        }

        if ($shifts->isEmpty()) {
            throw InvalidHourException::in('shifts', 'Informe ao menos um turno.');
        }

        return $shifts->toArray();
    }

    private static function normalizeNullableText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public function kind(): ?HourExceptionKind
    {
        return HourExceptionKind::tryFrom($this->kind);
    }

    // Nome do histórico: o tipo, o período e o dono, lidos dentro da mesma conta.
    public function getNameAttribute(): ?string
    {
        $row = DB::table('companies')
            ->where('account_id', $this->account_id)
            ->where('id', $this->company_id)
            ->first(['name', 'trade_name']);

        if ($row === null) {
            return null;
        }

        $owner = $this->establishment_id === null
            ? ($row->trade_name ?: $row->name)
            : (string) DB::table('establishments')->where('id', $this->establishment_id)->value('name');

        $period = substr((string) $this->starts_on, 0, 10) === substr((string) $this->ends_on, 0, 10)
            ? substr((string) $this->starts_on, 0, 10)
            : substr((string) $this->starts_on, 0, 10).' a '.substr((string) $this->ends_on, 0, 10);

        return ($this->kind()?->label() ?? $this->kind).' '.$period.' ('.$owner.')';
    }

    public static function auditType(): string
    {
        return 'hour-exception';
    }

    /** @return array<string,string> */
    public static function auditedFields(): array
    {
        return [
            'account_id' => 'Conta',
            'company_id' => 'Empresa',
            'establishment_id' => 'Estabelecimento',
            'kind' => 'Tipo',
            'starts_on' => 'Primeiro dia',
            'ends_on' => 'Último dia',
            'shifts' => 'Turnos',
            'note' => 'Observação pública',
            'applies_to_overrides' => 'Alcança unidades com horário próprio',
        ];
    }
}
