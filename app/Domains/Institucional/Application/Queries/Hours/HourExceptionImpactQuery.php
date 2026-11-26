<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Queries\Hours;

use App\Domains\Institucional\Application\Context\ProfileResolver;
use App\Domains\Institucional\Domain\Enums\HourExceptionKind;
use App\Domains\Institucional\Domain\Enums\ProfileSectionType;
use App\Domains\Institucional\Domain\Enums\ProfileState;
use App\Domains\Institucional\Domain\ValueObjects\Hours\HourExceptionImpact;
use App\Domains\Institucional\Domain\ValueObjects\ProfileOwner;
use Illuminate\Support\Facades\DB;

/**
 * Quais unidades uma exceção da empresa alcança (PD2-08). Classifica cada unidade ativa pelo modo efetivo de
 * `hours_weekly`, lido pelo `ProfileResolver`: quem herda é sempre alcançada, quem tem horário próprio só com
 * `applies_to_overrides`, e quem desativou a seção não exibe horário. As unidades vêm do próprio banco dentro da conta,
 * nunca do pedido do navegador.
 */
final readonly class HourExceptionImpactQuery
{
    public function __construct(private ProfileResolver $resolver) {}

    public function execute(ProfileOwner $company, HourExceptionKind $kind, string $startsOn, string $endsOn): HourExceptionImpact
    {
        $units = DB::table('establishments')
            ->where('account_id', $company->accountId)
            ->where('company_id', $company->companyId)
            ->where('active', true)
            ->orderBy('id')
            ->get(['id', 'code', 'name']);

        $this->resolver->warm($company->accountId, $company->companyId, array_values($units->pluck('id')->map(fn ($id): int => (int) $id)->all()));

        $inheriting = [];
        $overriding = [];
        $disabled = 0;

        foreach ($units as $unit) {
            $owner = ProfileOwner::establishment($company->accountId, $company->companyId, (int) $unit->id);
            $mode = $this->resolver->resolve($owner, ProfileSectionType::HoursWeekly, ProfileState::Published)->mode;
            $entry = ['id' => (int) $unit->id, 'code' => (string) $unit->code, 'name' => (string) $unit->name];

            match ($mode) {
                'override' => $overriding[] = $entry,
                'disabled' => $disabled++,
                default => $inheriting[] = $entry,
            };
        }

        $token = hash('sha256', json_encode([
            $company->companyId,
            $kind->value,
            $startsOn,
            $endsOn,
            array_column($inheriting, 'id'),
            array_column($overriding, 'id'),
            $disabled,
        ], JSON_THROW_ON_ERROR));

        $names = fn (array $list): array => array_map(
            fn (array $entry): array => ['code' => (string) $entry['code'], 'name' => (string) $entry['name']],
            $list,
        );

        return new HourExceptionImpact(array_values($names($inheriting)), array_values($names($overriding)), $disabled, $token);
    }
}
