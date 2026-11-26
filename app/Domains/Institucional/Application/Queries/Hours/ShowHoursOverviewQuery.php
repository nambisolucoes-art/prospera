<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Queries\Hours;

use App\Domains\Institucional\Application\Context\HoursResolver;
use App\Domains\Institucional\Domain\ValueObjects\Hours\EffectiveHours;
use App\Domains\Institucional\Domain\ValueObjects\ProfileOwner;
use App\Support\IanaTimezone;
use Carbon\CarbonImmutable;

/**
 * Resumo de horários para a tela do perfil institucional: o resultado efetivo agora (calculado a cada requisição, nunca
 * guardado), as exceções do dono, as exceções da empresa que alcançam a unidade e, na empresa, a situação de cada
 * unidade. Todo cálculo passa pelo `HoursResolver`.
 */
final readonly class ShowHoursOverviewQuery
{
    public function __construct(
        private HoursResolver $resolver,
        private ListHourExceptionsQuery $exceptions,
    ) {}

    /** @return array<string, mixed> */
    public function execute(ProfileOwner $owner, CarbonImmutable $now, bool $withTimezones): array
    {
        if ($owner->isEstablishment()) {
            $effective = $this->resolver->forUnit($owner, $now);
            $today = $effective === null ? $now->toDateString() : $effective->localDate;

            return [
                'now' => $effective?->toArray(),
                'timezone' => $effective?->timezone,
                'timezones' => $withTimezones ? IanaTimezone::options() : [],
                'exceptions' => $this->exceptions->execute($owner, $today),
                'inherited_exceptions' => $effective === null ? [] : $this->inherited($owner, $effective, $today),
                'units' => [],
            ];
        }

        $units = $this->resolver->forCompany($owner->accountId, $owner->companyId, null, $now);
        $today = $units === [] ? $now->toDateString() : min(array_map(fn (EffectiveHours $unit): string => $unit->localDate, $units));

        return [
            'now' => null,
            'timezone' => null,
            'timezones' => [],
            'exceptions' => $this->exceptions->execute($owner, $today),
            'inherited_exceptions' => [],
            'units' => array_values(array_map(fn (EffectiveHours $unit): array => [
                'code' => $unit->unitCode,
                'status' => $unit->status->value,
                'status_label' => $unit->status->label(),
                'timezone' => $unit->timezone,
                'hours_source' => $unit->hoursSource,
                'hours_mode' => $unit->hoursMode,
                'local_time' => $unit->localTime,
            ], $units)),
        ];
    }

    /**
     * Exceções da empresa que alcançam a unidade: todas quando ela herda o horário e só as que declaram
     * `applies_to_overrides` quando ela tem horário próprio. Com a seção desativada, nenhuma.
     *
     * @return list<array<string, mixed>>
     */
    private function inherited(ProfileOwner $unit, EffectiveHours $effective, string $today): array
    {
        if ($effective->hoursMode === 'disabled') {
            return [];
        }

        $company = ProfileOwner::company($unit->accountId, $unit->companyId);

        return array_values(array_filter(
            $this->exceptions->execute($company, $today),
            fn (array $row): bool => $effective->hoursMode !== 'override' || $row['applies_to_overrides'],
        ));
    }
}
