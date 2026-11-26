<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Context;

use App\Domains\Institucional\Application\Data\Profile\Sections\HoursWeeklyValue;
use App\Domains\Institucional\Application\Queries\Hours\ListHourExceptionsForDatesQuery;
use App\Domains\Institucional\Application\Queries\Hours\ListHoursUnitsQuery;
use App\Domains\Institucional\Domain\Enums\DayKind;
use App\Domains\Institucional\Domain\Enums\HourExceptionKind;
use App\Domains\Institucional\Domain\Enums\OpenStatus;
use App\Domains\Institucional\Domain\Enums\ProfileSectionType;
use App\Domains\Institucional\Domain\Enums\ProfileState;
use App\Domains\Institucional\Domain\Enums\Weekday;
use App\Domains\Institucional\Domain\ValueObjects\Hours\DaySchedule;
use App\Domains\Institucional\Domain\ValueObjects\Hours\EffectiveHours;
use App\Domains\Institucional\Domain\ValueObjects\Hours\Shifts;
use App\Domains\Institucional\Domain\ValueObjects\ProfileOwner;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;

/**
 * Horário efetivo das unidades num instante (seções 09.5 e 09.7 do plano 002). O horário semanal vem sempre do
 * `ProfileResolver` (a única porta das seções) e as exceções, de uma consulta única por empresa; o cálculo não conhece
 * a hora do servidor, só o instante recebido e o fuso IANA de cada unidade.
 *
 * Ordem, para o dia civil local da unidade: exceção que cobre o dia (a da própria unidade vence a da empresa; a da
 * empresa só alcança quem herda `hours_weekly` ou quando declara `applies_to_overrides`), senão o horário semanal efetivo,
 * senão "não informado". Dentro de um dono as exceções não se sobrepõem (a Action valida); se algum dado antigo
 * sobrepuser, vale o menor período e, no empate, o menor id, nunca a ordem do SQL.
 *
 * Binding `scoped`: sem estado entre requisições.
 */
final class HoursResolver
{
    public function __construct(
        private readonly ProfileResolver $profiles,
        private readonly ListHoursUnitsQuery $units,
        private readonly ListHourExceptionsForDatesQuery $exceptions,
    ) {}

    public function forUnit(ProfileOwner $unit, CarbonImmutable $instant): ?EffectiveHours
    {
        return $this->forCompany($unit->accountId, $unit->companyId, [(int) $unit->establishmentId], $instant)[$unit->establishmentId] ?? null;
    }

    /**
     * Todas as unidades pedidas (ou as da empresa) em três consultas, sem N+1.
     *
     * @param  list<int>|null  $establishmentIds
     * @return array<int, EffectiveHours> id da unidade para o horário efetivo; unidade de outro escopo não aparece
     */
    public function forCompany(int $accountId, int $companyId, ?array $establishmentIds, CarbonImmutable $instant): array
    {
        $units = $this->units->execute($accountId, $companyId, $establishmentIds);

        if ($units->isEmpty()) {
            return [];
        }

        $ids = array_values($units->keys()->map(fn ($id): int => (int) $id)->all());
        $this->profiles->warm($accountId, $companyId, $ids);

        $locals = $units->map(fn (object $unit): CarbonImmutable => $instant->setTimezone((string) $unit->timezone));
        $dates = $locals->map(fn (CarbonImmutable $local): string => $local->toDateString());
        $exceptions = $this->exceptions->execute($accountId, $companyId, $ids, (string) $dates->min(), (string) $dates->max());

        $result = [];

        foreach ($units as $id => $unit) {
            $result[(int) $id] = $this->unit(
                ProfileOwner::establishment($accountId, $companyId, (int) $id),
                $unit,
                $locals[$id],
                $exceptions,
            );
        }

        return $result;
    }

    /**
     * @param  array<int, array{id: int, establishment_id: int|null, kind: string, starts_on: string, ends_on: string, shifts: list<array{start: string, end: string}>|null, note: string|null, applies_to_overrides: bool}>  $exceptions
     */
    private function unit(ProfileOwner $owner, \stdClass $unit, CarbonImmutable $local, array $exceptions): EffectiveHours
    {
        $timezone = (string) $unit->timezone;
        $date = $local->toDateString();
        $time = $local->format('H:i');
        $validUntil = BusinessDate::startOfNextDay($date, $timezone);

        $weekly = $this->profiles->resolve($owner, ProfileSectionType::HoursWeekly, ProfileState::Published);

        $make = fn (OpenStatus $status, ?string $reason = null, ?DaySchedule $schedule = null, ?string $from = null, ?array $exception = null): EffectiveHours => new EffectiveHours(
            (string) $unit->code,
            $status,
            $reason,
            $schedule,
            $from,
            $weekly->source,
            $weekly->mode,
            $timezone,
            $date,
            $time,
            $exception,
            $validUntil,
        );

        if (! $unit->unit_active || ! $unit->company_active) {
            return $make(OpenStatus::Inactive);
        }

        if ($weekly->mode === 'disabled') {
            return $make(OpenStatus::Disabled);
        }

        $row = $this->exception($owner->establishmentId, $weekly->mode, $date, $exceptions);
        $minute = $local->hour * 60 + $local->minute;

        if ($row === null) {
            if ($weekly->source === 'platform') {
                return $make(OpenStatus::NotInformed);
            }

            $schedule = HoursWeeklyValue::restore($weekly->value ?? [])->days[Weekday::fromIso($local->dayOfWeekIso)->value];
            $status = $schedule->statusAt($minute);

            return $make($status, $status === OpenStatus::Closed ? $this->weeklyReason($schedule) : null, $schedule, 'weekly');
        }

        $kind = HourExceptionKind::from($row['kind']);
        $summary = [
            'id' => $row['id'],
            'kind' => $kind->value,
            'owner' => $row['establishment_id'] === null ? 'company' : 'establishment',
            'starts_on' => $row['starts_on'],
            'ends_on' => $row['ends_on'],
            'note' => $row['note'],
            'reached_override' => $row['establishment_id'] === null && $weekly->mode === 'override',
        ];

        if (! $kind->hasShifts()) {
            return $make(OpenStatus::Closed, $kind->value, DaySchedule::closed(), 'exception', $summary);
        }

        $schedule = DaySchedule::restore(['kind' => DayKind::Open->value, 'shifts' => $row['shifts'] ?? []]);
        $status = $schedule->statusAt($minute);

        return $make($status, $status === OpenStatus::Closed ? 'outside_shifts' : null, $schedule, 'exception', $summary);
    }

    /**
     * A exceção que vale para a unidade no dia, ou nulo. A da própria unidade vence; a da empresa só alcança quem não
     * tem horário próprio, salvo `applies_to_overrides`. Em qualquer dono, vale o menor período e, no empate, o menor id.
     *
     * @param  array<int, array{id: int, establishment_id: int|null, kind: string, starts_on: string, ends_on: string, shifts: list<array{start: string, end: string}>|null, note: string|null, applies_to_overrides: bool}>  $exceptions
     * @return array{id: int, establishment_id: int|null, kind: string, starts_on: string, ends_on: string, shifts: list<array{start: string, end: string}>|null, note: string|null, applies_to_overrides: bool}|null
     */
    private function exception(?int $establishmentId, string $mode, string $date, array $exceptions): ?array
    {
        $own = [];
        $corporate = [];

        foreach ($exceptions as $row) {
            if ($row['starts_on'] > $date || $row['ends_on'] < $date) {
                continue;
            }

            if ($row['establishment_id'] === $establishmentId) {
                $own[] = $row;
            } elseif ($row['establishment_id'] === null && ($mode !== 'override' || $row['applies_to_overrides'])) {
                $corporate[] = $row;
            }
        }

        $candidates = $own !== [] ? $own : $corporate;

        usort($candidates, fn (array $a, array $b): int => [$this->span($a), $a['id']] <=> [$this->span($b), $b['id']]);

        return $candidates[0] ?? null;
    }

    /** @param array{starts_on: string, ends_on: string} $row */
    private function span(array $row): int
    {
        return (int) CarbonImmutable::createFromFormat('!Y-m-d', $row['starts_on'])
            ->diffInDays(CarbonImmutable::createFromFormat('!Y-m-d', $row['ends_on']));
    }

    private function weeklyReason(DaySchedule $schedule): string
    {
        return $schedule->kind === DayKind::Closed ? 'closed_day' : 'outside_shifts';
    }
}
