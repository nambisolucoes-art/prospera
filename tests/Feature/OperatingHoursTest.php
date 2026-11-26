<?php

namespace Tests\Feature;

use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Auditoria\Domain\Models\AuditRecord;
use App\Domains\Institucional\Application\Context\HoursResolver;
use App\Domains\Institucional\Application\Context\ProfileResolver;
use App\Domains\Institucional\Domain\Enums\OpenStatus;
use App\Domains\Institucional\Domain\Models\HourException;
use App\Domains\Institucional\Domain\Models\ProfileSection;
use App\Domains\Institucional\Domain\ValueObjects\Hours\EffectiveHours;
use App\Domains\Institucional\Domain\ValueObjects\ProfileOwner;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\BuildsCommercialScenario;
use Tests\TestCase;

/**
 * S07 do plano 002: horário semanal com turnos, herança, exceções, regra corporativa sobre horário próprio e "aberto agora".
 * Datas de referência (2026): 10/06 é quarta, 13/06 é sábado e 14/06 é domingo.
 */
class OperatingHoursTest extends TestCase
{
    use BuildsCommercialScenario;
    use RefreshDatabase;

    private Client $gartan;

    private Company $roupas;

    private Company $express;

    private Establishment $sede;

    private Establishment $guanambi;

    private Establishment $caetite;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gartan = $this->account();
        $this->roupas = $this->company($this->gartan, 'Gartan Roupas', '11222333');
        $this->express = $this->company($this->gartan, 'Gartan Express', '44555666');
        $this->sede = $this->roupas->primaryEstablishment()->sole();
        $this->guanambi = $this->unit($this->roupas, 'Guanambi');
        $this->caetite = $this->unit($this->roupas, 'Caetité');
        $this->owner = $this->administrator($this->gartan);

        $this->actingAs($this->owner);
    }

    private function companyUrl(Company|string|null $company = null, string $suffix = ''): string
    {
        $company = $company instanceof Company ? $company : $this->roupas;

        return '/conta/empresa/'.$company->code.'/institucional'.$suffix;
    }

    private function unitUrl(Establishment $unit, string $suffix = ''): string
    {
        return '/conta/estabelecimento/'.$unit->code.'/institucional'.$suffix;
    }

    private function hours(): HoursResolver
    {
        $this->app->forgetScopedInstances();

        return app(HoursResolver::class);
    }

    private function at(Establishment $unit, string $localTime): EffectiveHours
    {
        $instant = CarbonImmutable::parse($localTime, $unit->fresh()->timezone);

        return $this->hours()->forUnit(ProfileOwner::establishment($this->gartan->id, $unit->company_id, $unit->id), $instant);
    }

    /** @return array{kind: string, shifts: list<array{start: string, end: string}>} */
    private function shifts(array ...$pairs): array
    {
        return ['kind' => 'open', 'shifts' => array_map(fn (array $pair): array => ['start' => $pair[0], 'end' => $pair[1]], $pairs)];
    }

    /** Segunda a sexta em dois turnos, sábado de manhã e domingo fechado. @return array<string, array<string,mixed>> */
    private function standardWeek(): array
    {
        $workday = $this->shifts(['08:00', '12:00'], ['14:00', '18:00']);

        return [
            'mon' => $workday, 'tue' => $workday, 'wed' => $workday, 'thu' => $workday, 'fri' => $workday,
            'sat' => $this->shifts(['08:00', '12:00']),
            'sun' => ['kind' => 'closed', 'shifts' => ''],
        ];
    }

    private function saveCompanyHours(?array $days = null, ?Company $company = null)
    {
        return $this->patchJson($this->companyUrl($company, '/secoes/hours_weekly'), [
            'mode' => 'override', 'value' => ['days' => $days ?? $this->standardWeek()],
        ]);
    }

    private function saveUnitHours(Establishment $unit, string $mode, ?array $days = null)
    {
        return $this->patchJson($this->unitUrl($unit, '/secoes/hours_weekly'), array_filter(
            ['mode' => $mode, 'value' => $days === null ? null : ['days' => $days]],
            fn ($item) => $item !== null,
        ));
    }

    /** @param array<string,mixed> $input */
    private function exception(string $url, array $input)
    {
        return $this->postJson($url, $input);
    }

    /** O resumo e o token que a tela confirma antes de gravar uma exceção da empresa. @return array<string,mixed> */
    private function impact(array $query, ?Company $company = null): array
    {
        return $this->getJson($this->companyUrl($company, '/excecoes/impacto').'?'.http_build_query($query))->assertOk()->json();
    }

    /** @param list<string> $slugs */
    private function managerOf(Company|Establishment $node, array $slugs): User
    {
        static $sequence = 0;

        $role = $this->role($this->gartan, 'Gestor de horários '.++$sequence, $slugs);
        $access = $node instanceof Company
            ? AccessNode::company($this->gartan->id, $node->id)
            : AccessNode::establishment($this->gartan->id, $node->company_id, $node->id);

        return $this->operator($this->gartan, [[$role, $access]]);
    }

    // Horário semanal ------------------------------------------------------------------------------------------------

    public function test_the_weekly_schedule_keeps_shifts_closed_days_and_appointment_only_days(): void
    {
        $days = $this->standardWeek();
        $days['fri'] = ['kind' => 'appointment', 'shifts' => [['start' => '09:00', 'end' => '11:00']]];
        $days['sat'] = ['kind' => 'appointment', 'shifts' => ''];
        $this->saveCompanyHours($days)->assertNoContent();

        $saved = ProfileSection::where('section', 'hours_weekly')->sole();
        $this->assertSame('override', $saved->mode);
        $this->assertSame(
            [['start' => '08:00', 'end' => '12:00'], ['start' => '14:00', 'end' => '18:00']],
            $saved->value['days']['mon']['shifts'],
        );
        $this->assertSame('appointment', $saved->value['days']['fri']['kind']);
        $this->assertSame([], $saved->value['days']['sat']['shifts']);
        $this->assertSame('closed', $saved->value['days']['sun']['kind']);

        // É operacional: o valor de trabalho e o publicado são gravados juntos.
        $this->assertSame($saved->value, $saved->published_value);
        $this->assertSame('override', $saved->published_mode);

        $section = collect($this->getJson($this->companyUrl())->assertOk()->json('sections'))->firstWhere('key', 'hours_weekly');
        $this->assertSame('operational', $section['kind']);
        $this->assertTrue($section['available']);
        $this->assertTrue($section['can_disable']);
        $this->assertFalse($section['pending']);
    }

    #[DataProvider('invalidWeeks')]
    public function test_invalid_weekly_schedules_are_refused_and_nothing_is_written(array $day, string $field): void
    {
        $this->saveCompanyHours(['mon' => $day])->assertUnprocessable()->assertJsonValidationErrors([$field]);

        $this->assertSame(0, ProfileSection::count());
        $this->assertSame(0, AuditRecord::where('entity_type', 'profile-section')->count());
    }

    public static function invalidWeeks(): array
    {
        $shift = fn (string $start, string $end): array => ['start' => $start, 'end' => $end];

        return [
            'formato da hora' => [['kind' => 'open', 'shifts' => [$shift('8h', '12:00')]], 'value.days.mon.shifts.0.start'],
            'hora inexistente' => [['kind' => 'open', 'shifts' => [$shift('25:00', '26:00')]], 'value.days.mon.shifts.0.start'],
            'minutos inexistentes' => [['kind' => 'open', 'shifts' => [$shift('08:60', '12:00')]], 'value.days.mon.shifts.0.start'],
            'início 24:00' => [['kind' => 'open', 'shifts' => [$shift('24:00', '24:00')]], 'value.days.mon.shifts.0.start'],
            'fim antes do início' => [['kind' => 'open', 'shifts' => [$shift('12:00', '08:00')]], 'value.days.mon.shifts.0.end'],
            'atravessa a meia-noite' => [['kind' => 'open', 'shifts' => [$shift('22:00', '02:00')]], 'value.days.mon.shifts.0.end'],
            'fim igual ao início' => [['kind' => 'open', 'shifts' => [$shift('08:00', '08:00')]], 'value.days.mon.shifts.0.end'],
            'sobreposição' => [['kind' => 'open', 'shifts' => [$shift('08:00', '12:00'), $shift('11:00', '14:00')]], 'value.days.mon.shifts.1.start'],
            'fora de ordem' => [['kind' => 'open', 'shifts' => [$shift('14:00', '18:00'), $shift('08:00', '12:00')]], 'value.days.mon.shifts.1.start'],
            'turnos demais' => [['kind' => 'open', 'shifts' => array_map(fn (int $hour): array => $shift(sprintf('%02d:00', $hour), sprintf('%02d:30', $hour)), range(6, 12))], 'value.days.mon.shifts'],
            'dia aberto sem turnos' => [['kind' => 'open', 'shifts' => ''], 'value.days.mon.shifts'],
            'dia fechado com turnos' => [['kind' => 'closed', 'shifts' => [$shift('08:00', '12:00')]], 'value.days.mon.shifts'],
            'tipo inválido' => [['kind' => 'maybe', 'shifts' => ''], 'value.days.mon.kind'],
            'tipo nulo' => [['kind' => null, 'shifts' => ''], 'value.days.mon.kind'],
            'turnos nulos num dia aberto' => [['kind' => 'open', 'shifts' => null], 'value.days.mon.shifts'],
            'turno sem fim' => [['kind' => 'open', 'shifts' => [['start' => '08:00']]], 'value.days.mon.shifts.0.end'],
        ];
    }

    public function test_the_end_of_the_day_is_valid_and_unknown_days_are_refused(): void
    {
        $this->saveCompanyHours(['mon' => $this->shifts(['18:00', '24:00']), 'tue' => $this->shifts(['00:00', '06:00'])])->assertNoContent();
        $this->assertSame('24:00', ProfileSection::sole()->value['days']['mon']['shifts'][0]['end']);

        $this->saveCompanyHours(['xyz' => $this->shifts(['08:00', '12:00'])])->assertUnprocessable()
            ->assertJsonValidationErrors(['value.days']);
        $this->patchJson($this->companyUrl('', '/secoes/hours_weekly'), ['mode' => 'override', 'value' => ['days' => 'segunda']])
            ->assertUnprocessable();
    }

    public function test_a_partial_patch_keeps_the_days_that_were_not_sent(): void
    {
        $this->saveCompanyHours()->assertNoContent();

        // Só a terça muda; a quarta troca o tipo sem reenviar turnos; o sábado fecha e os turnos somem.
        $this->saveCompanyHours([
            'tue' => $this->shifts(['10:00', '16:00']),
            'wed' => ['kind' => 'appointment'],
            'sat' => ['kind' => 'closed'],
        ])->assertNoContent();

        $days = ProfileSection::sole()->value['days'];
        $this->assertSame([['start' => '08:00', 'end' => '12:00'], ['start' => '14:00', 'end' => '18:00']], $days['mon']['shifts']);
        $this->assertSame([['start' => '10:00', 'end' => '16:00']], $days['tue']['shifts']);
        $this->assertSame('appointment', $days['wed']['kind']);
        $this->assertCount(2, $days['wed']['shifts']);
        $this->assertSame(['kind' => 'closed', 'shifts' => []], $days['sat']);
    }

    // Herança --------------------------------------------------------------------------------------------------------

    public function test_units_inherit_follow_the_company_and_keep_their_own_hours_when_personalized(): void
    {
        $this->saveCompanyHours()->assertNoContent();
        $unit = fn (Establishment $establishment): EffectiveHours => $this->at($establishment, '2026-06-10 09:00');

        $this->assertSame('company', $unit($this->guanambi)->hoursSource);
        $this->assertSame(OpenStatus::Open, $unit($this->guanambi)->status);

        // A unidade personaliza: só quinta, das 10 às 20. Quarta continua igual à da empresa? Não: o valor próprio é inteiro.
        $own = $this->standardWeek();
        $own['wed'] = $this->shifts(['10:00', '20:00']);
        $this->saveUnitHours($this->guanambi, 'override', $own)->assertNoContent();
        $this->assertSame('establishment', $unit($this->guanambi)->hoursSource);
        $this->assertSame(OpenStatus::Closed, $unit($this->guanambi)->status);

        // A empresa muda: quem herda acompanha, quem personalizou não, e nenhuma linha nasce nas unidades.
        $changed = $this->standardWeek();
        $changed['wed'] = $this->shifts(['06:00', '07:00']);
        $this->saveCompanyHours($changed)->assertNoContent();
        $this->assertSame(OpenStatus::Closed, $unit($this->caetite)->status);
        $this->assertSame('company', $unit($this->caetite)->hoursSource);
        $this->assertSame(OpenStatus::Closed, $unit($this->guanambi)->status);
        $this->assertSame('establishment', $unit($this->guanambi)->hoursSource);
        $this->assertSame(1, ProfileSection::where('section', 'hours_weekly')->whereNotNull('establishment_id')->count());

        // Voltar a herdar usa o valor ATUAL da empresa, na hora (operacional).
        $this->saveUnitHours($this->guanambi, 'inherit')->assertNoContent();
        $this->assertSame('company', $unit($this->guanambi)->hoursSource);
        $this->assertSame(OpenStatus::Closed, $unit($this->guanambi)->status);
        $this->assertSame('06:00', $unit($this->guanambi)->schedule->toArray()['shifts'][0]['start']);
    }

    public function test_an_empty_override_is_not_inheritance_and_a_disabled_unit_shows_no_hours(): void
    {
        $this->saveCompanyHours()->assertNoContent();

        // Vazio intencional: todos os dias fechados, próprio da unidade, sem herdar a grade da empresa.
        $closed = array_map(fn (): array => ['kind' => 'closed', 'shifts' => ''], $this->standardWeek());
        $this->saveUnitHours($this->guanambi, 'override', $closed)->assertNoContent();
        $effective = $this->at($this->guanambi, '2026-06-10 09:00');
        $this->assertSame(OpenStatus::Closed, $effective->status);
        $this->assertSame('closed_day', $effective->closedReason);
        $this->assertSame('establishment', $effective->hoursSource);
        $this->assertSame(OpenStatus::Open, $this->at($this->caetite, '2026-06-10 09:00')->status);

        // Desativar a seção: nada é exibido, e o horário de outra unidade não muda.
        $this->saveUnitHours($this->guanambi, 'disabled')->assertNoContent();
        $this->assertSame(OpenStatus::Disabled, $this->at($this->guanambi, '2026-06-10 09:00')->status);
        $this->assertNull($this->at($this->guanambi, '2026-06-10 09:00')->schedule);
        $this->assertSame(OpenStatus::Open, $this->at($this->caetite, '2026-06-10 09:00')->status);

        // A empresa só personaliza.
        $this->patchJson($this->companyUrl('', '/secoes/hours_weekly'), ['mode' => 'disabled'])->assertUnprocessable();
        $this->patchJson($this->companyUrl('', '/secoes/hours_weekly'), ['mode' => 'inherit'])->assertUnprocessable();
    }

    public function test_the_unit_edit_never_writes_to_the_company_or_to_another_unit(): void
    {
        $this->saveCompanyHours()->assertNoContent();
        $company = fn (): array => [
            ProfileSection::where('establishment_id', null)->sole()->getRawOriginal('value'),
            ProfileSection::where('establishment_id', null)->sole()->getRawOriginal('published_at'),
            AuditRecord::where('establishment_id', null)->count(),
        ];
        $before = $company();

        $this->saveUnitHours($this->guanambi, 'override', ['mon' => $this->shifts(['09:00', '10:00'])])->assertNoContent();

        $this->assertSame($before, $company());
        $this->assertSame(0, ProfileSection::where('establishment_id', $this->caetite->id)->count());
        $this->assertSame('company', $this->at($this->caetite, '2026-06-10 09:00')->hoursSource);
    }

    public function test_never_configured_hours_are_not_informed_and_not_a_closed_week(): void
    {
        $effective = $this->at($this->guanambi, '2026-06-10 09:00');

        $this->assertSame(OpenStatus::NotInformed, $effective->status);
        $this->assertSame('platform', $effective->hoursSource);
        $this->assertNull($effective->schedule);
    }

    // Aberto agora ---------------------------------------------------------------------------------------------------

    public function test_open_now_follows_the_shifts_of_the_local_day(): void
    {
        $this->saveCompanyHours()->assertNoContent();

        $cases = [
            '2026-06-10 07:59' => OpenStatus::Closed,
            '2026-06-10 08:00' => OpenStatus::Open,
            '2026-06-10 09:00' => OpenStatus::Open,
            '2026-06-10 11:59' => OpenStatus::Open,
            '2026-06-10 12:00' => OpenStatus::Closed,
            '2026-06-10 12:30' => OpenStatus::Closed,
            '2026-06-10 14:00' => OpenStatus::Open,
            '2026-06-10 15:00' => OpenStatus::Open,
            '2026-06-10 18:00' => OpenStatus::Closed,
            '2026-06-10 19:00' => OpenStatus::Closed,
            '2026-06-13 11:00' => OpenStatus::Open,
            '2026-06-13 15:00' => OpenStatus::Closed,
            '2026-06-14 10:00' => OpenStatus::Closed,
        ];

        foreach ($cases as $moment => $expected) {
            $this->assertSame($expected, $this->at($this->guanambi, $moment)->status, $moment);
        }

        $this->assertSame('outside_shifts', $this->at($this->guanambi, '2026-06-10 12:30')->closedReason);
        $this->assertSame('closed_day', $this->at($this->guanambi, '2026-06-14 10:00')->closedReason);
        $this->assertSame('weekly', $this->at($this->guanambi, '2026-06-10 09:00')->scheduleSource);
        $this->assertSame('America/Sao_Paulo', $this->at($this->guanambi, '2026-06-10 09:00')->timezone);
    }

    public function test_appointment_days_report_by_appointment_inside_the_windows_only(): void
    {
        $days = $this->standardWeek();
        $days['wed'] = ['kind' => 'appointment', 'shifts' => [['start' => '09:00', 'end' => '11:00']]];
        $days['thu'] = ['kind' => 'appointment', 'shifts' => ''];
        $this->saveCompanyHours($days)->assertNoContent();

        $this->assertSame(OpenStatus::ByAppointment, $this->at($this->sede, '2026-06-10 10:00')->status);
        $this->assertSame(OpenStatus::Closed, $this->at($this->sede, '2026-06-10 12:00')->status);
        $this->assertSame(OpenStatus::ByAppointment, $this->at($this->sede, '2026-06-11 03:00')->status);
        $this->assertFalse($this->at($this->sede, '2026-06-10 10:00')->isOpen());
    }

    public function test_an_inactive_unit_or_company_is_never_open(): void
    {
        $this->saveCompanyHours()->assertNoContent();
        $this->guanambi->forceFill(['active' => false])->saveQuietly();

        $this->assertSame(OpenStatus::Inactive, $this->at($this->guanambi, '2026-06-10 09:00')->status);
        $this->assertSame(OpenStatus::Open, $this->at($this->caetite, '2026-06-10 09:00')->status);

        $this->roupas->forceFill(['active' => false])->saveQuietly();
        $this->assertSame(OpenStatus::Inactive, $this->at($this->caetite, '2026-06-10 09:00')->status);
    }

    // Exceções -------------------------------------------------------------------------------------------------------

    public function test_a_company_holiday_reaches_the_units_that_inherit_and_not_the_ones_with_own_hours(): void
    {
        $this->saveCompanyHours()->assertNoContent();
        $this->saveUnitHours($this->guanambi, 'override', $this->standardWeek())->assertNoContent();
        $this->saveUnitHours($this->caetite, 'override', $this->standardWeek())->assertNoContent();

        $this->exception($this->companyUrl('', '/excecoes'), [
            'kind' => 'holiday', 'starts_on' => '2026-06-10', 'note' => 'Aniversário da cidade',
        ])->assertNoContent();

        $sede = $this->at($this->sede, '2026-06-10 09:00');
        $this->assertSame(OpenStatus::Closed, $sede->status);
        $this->assertSame('holiday', $sede->closedReason);
        $this->assertSame('exception', $sede->scheduleSource);
        $this->assertSame('company', $sede->exception['owner']);
        $this->assertSame('Aniversário da cidade', $sede->exception['note']);
        $this->assertFalse($sede->exception['reached_override']);

        // B e C têm horário próprio: a exceção da empresa não os alcança.
        $this->assertSame(OpenStatus::Open, $this->at($this->guanambi, '2026-06-10 09:00')->status);
        $this->assertSame(OpenStatus::Open, $this->at($this->caetite, '2026-06-10 09:00')->status);
        $this->assertNull($this->at($this->guanambi, '2026-06-10 09:00')->exception);

        // O dia seguinte já não é afetado, em nenhuma unidade.
        $this->assertSame(OpenStatus::Open, $this->at($this->sede, '2026-06-11 09:00')->status);
    }

    public function test_applies_to_overrides_needs_the_confirmed_impact_and_reaches_every_unit(): void
    {
        $this->saveCompanyHours()->assertNoContent();
        $this->saveUnitHours($this->guanambi, 'override', $this->standardWeek())->assertNoContent();
        $this->saveUnitHours($this->caetite, 'override', $this->standardWeek())->assertNoContent();
        $input = ['kind' => 'temporary_closure', 'starts_on' => '2026-06-10', 'ends_on' => '2026-06-12', 'applies_to_overrides' => true];

        // Sem o token do resumo, nada é gravado.
        $this->exception($this->companyUrl('', '/excecoes'), $input)->assertUnprocessable()->assertJsonValidationErrors(['impact']);
        $this->assertSame(0, HourException::count());

        $impact = $this->impact(['kind' => 'temporary_closure', 'starts_on' => '2026-06-10', 'ends_on' => '2026-06-12']);
        $this->assertSame([$this->sede->code], array_column($impact['inheriting'], 'code'));
        $this->assertEqualsCanonicalizing([$this->guanambi->code, $this->caetite->code], array_column($impact['overriding'], 'code'));

        $this->exception($this->companyUrl('', '/excecoes'), $input + ['impact_token' => $impact['token']])->assertNoContent();

        foreach ([$this->sede, $this->guanambi, $this->caetite] as $unit) {
            $effective = $this->at($unit, '2026-06-11 09:00');
            $this->assertSame(OpenStatus::Closed, $effective->status, $unit->code);
            $this->assertSame('temporary_closure', $effective->closedReason);
        }

        $this->assertTrue($this->at($this->guanambi, '2026-06-11 09:00')->exception['reached_override']);
        $this->assertFalse($this->at($this->sede, '2026-06-11 09:00')->exception['reached_override']);
        // Depois do período a unidade com horário próprio volta ao normal (sábado de manhã).
        $this->assertSame(OpenStatus::Open, $this->at($this->guanambi, '2026-06-13 09:00')->status);
        $this->assertSame(OpenStatus::Closed, $this->at($this->guanambi, '2026-06-12 09:00')->status);
    }

    public function test_a_stale_or_forged_impact_confirmation_is_refused_without_side_effects(): void
    {
        $this->saveCompanyHours()->assertNoContent();
        $this->saveUnitHours($this->guanambi, 'override', $this->standardWeek())->assertNoContent();
        $query = ['kind' => 'holiday', 'starts_on' => '2026-06-10', 'ends_on' => '2026-06-10'];
        $input = $query + ['applies_to_overrides' => true];
        $stale = $this->impact($query)['token'];

        // Uma alteração concorrente muda o alcance depois do resumo: a unidade volta a herdar.
        $this->saveUnitHours($this->guanambi, 'inherit')->assertNoContent();
        $this->exception($this->companyUrl('', '/excecoes'), $input + ['impact_token' => $stale])
            ->assertUnprocessable()->assertJsonValidationErrors(['impact']);
        $this->assertSame(0, HourException::count());

        // Resumo adulterado ou de outro período.
        $this->exception($this->companyUrl('', '/excecoes'), $input + ['impact_token' => str_repeat('a', 64)])->assertUnprocessable();
        $other = $this->impact(['kind' => 'holiday', 'starts_on' => '2026-06-11', 'ends_on' => '2026-06-11'])['token'];
        $this->exception($this->companyUrl('', '/excecoes'), $input + ['impact_token' => $other])->assertUnprocessable();
        $this->assertSame(0, HourException::count());

        // Com o resumo atual, grava.
        $this->exception($this->companyUrl('', '/excecoes'), $input + ['impact_token' => $this->impact($query)['token']])->assertNoContent();
        $this->assertSame(1, HourException::count());
    }

    public function test_a_unit_exception_applies_only_to_that_unit_and_beats_the_company_one(): void
    {
        $this->saveCompanyHours()->assertNoContent();
        $this->exception($this->companyUrl('', '/excecoes'), ['kind' => 'holiday', 'starts_on' => '2026-06-10'])->assertNoContent();
        $this->exception($this->unitUrl($this->guanambi, '/excecoes'), [
            'kind' => 'special_hours', 'starts_on' => '2026-06-10',
            'shifts' => [['start' => '10:00', 'end' => '14:00']], 'note' => 'Plantão',
        ])->assertNoContent();
        $this->exception($this->unitUrl($this->caetite, '/excecoes'), [
            'kind' => 'temporary_closure', 'starts_on' => '2026-06-15', 'ends_on' => '2026-06-17',
        ])->assertNoContent();

        // Guanambi abre no horário especial mesmo com o feriado da empresa; a sede e Caetité seguem o feriado.
        $guanambi = $this->at($this->guanambi, '2026-06-10 11:00');
        $this->assertSame(OpenStatus::Open, $guanambi->status);
        $this->assertSame('establishment', $guanambi->exception['owner']);
        $this->assertSame(OpenStatus::Closed, $this->at($this->guanambi, '2026-06-10 15:00')->status);
        $this->assertSame('outside_shifts', $this->at($this->guanambi, '2026-06-10 15:00')->closedReason);
        $this->assertSame('holiday', $this->at($this->caetite, '2026-06-10 11:00')->closedReason);

        // O fechamento de Caetité não fecha as outras unidades, e as duas pontas do período contam.
        $this->assertSame(OpenStatus::Open, $this->at($this->guanambi, '2026-06-16 09:00')->status);
        $this->assertSame(OpenStatus::Open, $this->at($this->sede, '2026-06-16 09:00')->status);
        $this->assertSame(OpenStatus::Closed, $this->at($this->caetite, '2026-06-15 09:00')->status);
        $this->assertSame(OpenStatus::Closed, $this->at($this->caetite, '2026-06-17 23:59')->status);
        $this->assertSame(OpenStatus::Open, $this->at($this->caetite, '2026-06-18 09:00')->status);
        $this->assertSame(OpenStatus::Open, $this->at($this->caetite, '2026-06-12 09:00')->status);
    }

    public function test_exception_periods_are_validated_and_cannot_overlap_within_the_same_owner(): void
    {
        $url = $this->companyUrl('', '/excecoes');
        $this->saveCompanyHours()->assertNoContent();

        $this->exception($url, ['kind' => 'holiday', 'starts_on' => '2026-06-10', 'ends_on' => '2026-06-09'])
            ->assertUnprocessable()->assertJsonValidationErrors(['ends_on']);
        $this->exception($url, ['kind' => 'holiday', 'starts_on' => '2026-02-30'])->assertUnprocessable()->assertJsonValidationErrors(['starts_on']);
        $this->exception($url, ['kind' => 'holiday', 'starts_on' => '10/06/2026'])->assertUnprocessable()->assertJsonValidationErrors(['starts_on']);
        $this->exception($url, ['kind' => 'holiday', 'starts_on' => '2026-01-01', 'ends_on' => '2027-06-01'])
            ->assertUnprocessable()->assertJsonValidationErrors(['ends_on']);
        $this->exception($url, ['kind' => 'weird', 'starts_on' => '2026-06-10'])->assertUnprocessable()->assertJsonValidationErrors(['kind']);
        $this->exception($url, ['kind' => 'special_hours', 'starts_on' => '2026-06-10'])->assertUnprocessable()->assertJsonValidationErrors(['shifts']);
        $this->exception($url, ['kind' => 'special_hours', 'starts_on' => '2026-06-10', 'shifts' => [['start' => '10:00', 'end' => '09:00']]])
            ->assertUnprocessable();
        $this->exception($url, ['kind' => 'holiday', 'starts_on' => '2026-06-10', 'shifts' => [['start' => '10:00', 'end' => '12:00']]])
            ->assertUnprocessable()->assertJsonValidationErrors(['shifts']);
        $this->exception($url, ['kind' => 'holiday', 'starts_on' => '2026-06-10', 'note' => str_repeat('x', 161)])
            ->assertUnprocessable()->assertJsonValidationErrors(['note']);
        $this->exception($url, ['kind' => 'holiday', 'starts_on' => null])->assertUnprocessable();
        $this->exception($url, ['kind' => 'holiday', 'starts_on' => '2026-06-10', 'applies_to_overrides' => 'talvez'])->assertUnprocessable();
        $this->assertSame(0, HourException::count());

        // Texto simples: HTML some e o espaço é normalizado.
        $this->exception($url, ['kind' => 'holiday', 'starts_on' => '2026-06-10', 'ends_on' => '2026-06-12', 'note' => ' <b>Feriado</b>   local '])
            ->assertNoContent();
        $this->assertSame('Feriado local', HourException::sole()->note);

        // Qualquer cruzamento com o período existente é recusado, nas pontas e dentro; o mesmo dia noutro dono passa.
        foreach ([['2026-06-12', '2026-06-14'], ['2026-06-08', '2026-06-10'], ['2026-06-11', '2026-06-11'], ['2026-06-01', '2026-06-30']] as [$from, $to]) {
            $this->exception($url, ['kind' => 'holiday', 'starts_on' => $from, 'ends_on' => $to])
                ->assertUnprocessable()->assertJsonValidationErrors(['starts_on']);
        }

        $this->exception($url, ['kind' => 'holiday', 'starts_on' => '2026-06-13'])->assertNoContent();
        $this->exception($this->unitUrl($this->guanambi, '/excecoes'), ['kind' => 'holiday', 'starts_on' => '2026-06-10'])->assertNoContent();
        $this->exception($this->companyUrl($this->express, '/excecoes'), ['kind' => 'holiday', 'starts_on' => '2026-06-10'])->assertNoContent();
        $this->assertSame(4, HourException::count());

        // Editar sem mudar o período não conflita consigo mesma; mover para cima de outra, sim.
        $first = HourException::where('establishment_id', null)->where('company_id', $this->roupas->id)->orderBy('starts_on')->first();
        // A edição substitui os valores (como o formulário envia): o corpo traz tipo e datas de novo.
        $same = ['kind' => 'holiday', 'starts_on' => '2026-06-10', 'ends_on' => '2026-06-12'];
        $this->patchJson($url.'/'.$first->id, $same + ['note' => 'Novo texto'])->assertNoContent();
        $this->patchJson($url.'/'.$first->id, [...$same, 'ends_on' => '2026-06-13'])->assertUnprocessable();
        $this->patchJson($url.'/'.$first->id, ['note' => 'sem tipo nem datas'])->assertUnprocessable()
            ->assertJsonValidationErrors(['kind', 'starts_on']);
        $this->assertSame('2026-06-12', substr($first->fresh()->ends_on, 0, 10));
        $this->assertSame('Novo texto', $first->fresh()->note);
    }

    public function test_overlapping_legacy_rows_resolve_by_smallest_period_then_id_and_the_check_reports_them(): void
    {
        $this->saveCompanyHours()->assertNoContent();
        $row = fn (string $kind, string $from, string $to): array => [
            'account_id' => $this->gartan->id, 'company_id' => $this->roupas->id, 'establishment_id' => null,
            'kind' => $kind, 'starts_on' => $from, 'ends_on' => $to, 'shifts' => null, 'created_at' => now(), 'updated_at' => now(),
        ];

        DB::table('hour_exceptions')->insert([$row('temporary_closure', '2026-06-01', '2026-06-30'), $row('holiday', '2026-06-10', '2026-06-10')]);

        // O período menor (o feriado) vence o maior, quaisquer que sejam a ordem das linhas e a do SQL.
        $this->assertSame('holiday', $this->at($this->sede, '2026-06-10 09:00')->closedReason);
        $this->assertSame('temporary_closure', $this->at($this->sede, '2026-06-11 09:00')->closedReason);

        $this->artisan('institutional:check')->assertFailed();
    }

    public function test_editing_and_deleting_exceptions_are_audited_in_the_owner_node(): void
    {
        $this->saveCompanyHours()->assertNoContent();
        $this->exception($this->unitUrl($this->guanambi, '/excecoes'), ['kind' => 'holiday', 'starts_on' => '2026-06-10'])->assertNoContent();
        $exception = HourException::sole();

        $created = AuditRecord::where('entity_type', 'hour-exception')->where('event', 'created')->sole();
        $this->assertSame($this->roupas->id, $created->company_id);
        $this->assertSame($this->guanambi->id, $created->establishment_id);

        // Sem mudança, sem evento.
        $this->patchJson($this->unitUrl($this->guanambi, '/excecoes/'.$exception->id), ['kind' => 'holiday', 'starts_on' => '2026-06-10'])->assertNoContent();
        $this->assertSame(1, AuditRecord::where('entity_type', 'hour-exception')->count());

        $this->patchJson($this->unitUrl($this->guanambi, '/excecoes/'.$exception->id), ['kind' => 'temporary_closure', 'starts_on' => '2026-06-10', 'ends_on' => '2026-06-12'])->assertNoContent();
        $updated = AuditRecord::where('entity_type', 'hour-exception')->where('event', 'updated')->sole();
        $this->assertSame($this->guanambi->id, $updated->establishment_id);

        $this->deleteJson($this->unitUrl($this->guanambi, '/excecoes/'.$exception->id))->assertNoContent();
        $this->assertSame(0, HourException::count());
        $this->assertSame(1, AuditRecord::where('entity_type', 'hour-exception')->where('event', 'deleted')->count());
        $this->assertSame(0, AuditRecord::where('entity_type', 'hour-exception')->where('establishment_id', $this->caetite->id)->count());
    }

    public function test_hours_and_corporate_exceptions_write_one_event_in_the_written_node_and_none_for_inheriting_units(): void
    {
        $this->saveCompanyHours()->assertNoContent();
        $this->exception($this->companyUrl('', '/excecoes'), ['kind' => 'holiday', 'starts_on' => '2026-06-10'])->assertNoContent();

        $events = AuditRecord::whereIn('entity_type', ['profile-section', 'hour-exception'])->get();
        $this->assertCount(2, $events);
        $this->assertSame([null], $events->pluck('establishment_id')->unique()->values()->all());
        $this->assertSame([$this->roupas->id], $events->pluck('company_id')->unique()->values()->all());

        // Mesmo conteúdo, nenhum evento novo.
        $this->saveCompanyHours()->assertNoContent();
        $this->assertSame(2, AuditRecord::whereIn('entity_type', ['profile-section', 'hour-exception'])->count());

        $this->saveUnitHours($this->guanambi, 'override', $this->standardWeek())->assertNoContent();
        $this->saveUnitHours($this->guanambi, 'disabled')->assertNoContent();
        $this->saveUnitHours($this->guanambi, 'inherit')->assertNoContent();
        $unitEvents = AuditRecord::where('entity_type', 'profile-section')->where('establishment_id', $this->guanambi->id)->get();
        $this->assertSame(['created', 'updated', 'updated'], $unitEvents->pluck('event')->all());
    }

    public function test_a_failed_exception_save_leaves_no_row_and_no_audit_event(): void
    {
        $this->saveCompanyHours()->assertNoContent();
        $events = AuditRecord::count();

        DB::listen(function ($query): void {
            if (str_starts_with($query->sql, 'insert into "hour_exceptions"')) {
                throw new \RuntimeException('falha simulada');
            }
        });
        $this->withoutExceptionHandling();

        try {
            $this->exception($this->companyUrl('', '/excecoes'), ['kind' => 'holiday', 'starts_on' => '2026-06-10']);
            $this->fail('A gravação deveria falhar.');
        } catch (\RuntimeException $failure) {
            $this->assertSame('falha simulada', $failure->getMessage());
        }

        $this->assertSame(0, HourException::count());
        $this->assertSame($events, AuditRecord::count());
    }

    // Segurança ------------------------------------------------------------------------------------------------------

    public function test_view_alone_cannot_write_and_each_node_only_reaches_its_own_hours(): void
    {
        $viewer = $this->managerOf($this->roupas, ['account.institutional.view']);
        $editor = $this->managerOf($this->roupas, ['account.institutional.view', 'account.institutional.update']);
        $unitManager = $this->managerOf($this->guanambi, ['account.institutional.view', 'account.institutional.update']);
        $this->saveCompanyHours()->assertNoContent();
        $exception = HourException::create([
            'account_id' => $this->gartan->id, 'company_id' => $this->roupas->id, 'establishment_id' => $this->caetite->id,
            'kind' => 'holiday', 'starts_on' => '2026-06-10', 'ends_on' => '2026-06-10',
        ]);
        $snapshot = [HourException::count(), ProfileSection::count(), AuditRecord::count()];

        $this->actingAs($viewer);
        $this->getJson($this->companyUrl())->assertOk()->assertJsonCount(3, 'hours.units');
        $this->exception($this->companyUrl('', '/excecoes'), ['kind' => 'holiday', 'starts_on' => '2026-07-01'])->assertForbidden();
        $this->exception($this->unitUrl($this->guanambi, '/excecoes'), ['kind' => 'holiday', 'starts_on' => '2026-07-01'])->assertForbidden();
        $this->patchJson($this->unitUrl($this->guanambi, '/fuso'), ['timezone' => 'America/Manaus'])->assertForbidden();
        $this->getJson($this->companyUrl('', '/excecoes/impacto').'?kind=holiday&starts_on=2026-07-01')->assertForbidden();

        // Gestor de unidade: só a própria unidade; a de Caetité, a empresa e a Express não existem para ele.
        $this->actingAs($unitManager);
        $this->exception($this->unitUrl($this->guanambi, '/excecoes'), ['kind' => 'holiday', 'starts_on' => '2026-07-01'])->assertNoContent();
        $this->exception($this->unitUrl($this->caetite, '/excecoes'), ['kind' => 'holiday', 'starts_on' => '2026-07-01'])->assertNotFound();
        $this->exception($this->companyUrl('', '/excecoes'), ['kind' => 'holiday', 'starts_on' => '2026-07-01'])->assertNotFound();
        $this->patchJson($this->unitUrl($this->caetite, '/fuso'), ['timezone' => 'America/Manaus'])->assertNotFound();
        $this->deleteJson($this->unitUrl($this->caetite, '/excecoes/'.$exception->id))->assertNotFound();
        $this->patchJson($this->unitUrl($this->guanambi, '/excecoes/'.$exception->id), ['note' => 'X'])->assertNotFound();
        $this->deleteJson($this->unitUrl($this->guanambi, '/excecoes/'.$exception->id))->assertNotFound();
        $this->assertSame(1, HourException::where('id', $exception->id)->count());

        // Gestor da empresa: a exceção de uma unidade dele não é editável pela rota da empresa.
        $this->actingAs($editor);
        $this->patchJson($this->companyUrl('', '/excecoes/'.$exception->id), ['note' => 'X'])->assertNotFound();
        $this->deleteJson($this->companyUrl('', '/excecoes/'.$exception->id))->assertNotFound();
        $this->exception($this->companyUrl($this->express, '/excecoes'), ['kind' => 'holiday', 'starts_on' => '2026-07-01'])->assertNotFound();
        $this->assertNull($exception->fresh()->note);

        $this->assertSame([$snapshot[0] + 1, $snapshot[1], $snapshot[2] + 1], [HourException::count(), ProfileSection::count(), AuditRecord::count()]);
    }

    public function test_tenants_do_not_share_hours_or_exceptions(): void
    {
        $this->saveCompanyHours()->assertNoContent();
        $this->exception($this->companyUrl('', '/excecoes'), ['kind' => 'holiday', 'starts_on' => '2026-06-10'])->assertNoContent();
        $exception = HourException::sole();

        $other = $this->account('Outro Grupo');
        $foreign = $this->company($other, 'Outro', '77888999');
        $stranger = $this->administrator($other);
        $this->actingAs($stranger);

        $this->getJson($this->companyUrl())->assertNotFound();
        $this->exception($this->companyUrl('', '/excecoes'), ['kind' => 'holiday', 'starts_on' => '2026-07-01'])->assertNotFound();
        $this->patchJson($this->companyUrl('', '/excecoes/'.$exception->id), ['note' => 'X'])->assertNotFound();
        $this->deleteJson($this->companyUrl('', '/excecoes/'.$exception->id))->assertNotFound();
        $this->patchJson($this->unitUrl($this->guanambi, '/fuso'), ['timezone' => 'America/Manaus'])->assertNotFound();
        $this->assertSame(1, HourException::count());

        // A própria empresa dele aceita o mesmo identificador de pedido sem tocar nas exceções da Gartan.
        $this->exception($this->companyUrl($foreign, '/excecoes'), ['kind' => 'holiday', 'starts_on' => '2026-06-10'])->assertNoContent();
        $this->assertNull($exception->fresh()->note);
        $this->assertSame(0, AuditRecord::where('account_id', $this->gartan->id)->where('actor_id', $stranger->id)->count());

        // O resolvedor não devolve unidade de outra conta nem de outra empresa.
        $this->actingAs($this->owner);
        $this->assertSame([], $this->hours()->forCompany($other->id, $this->roupas->id, null, CarbonImmutable::now()));
        $this->assertSame([], $this->hours()->forCompany($this->gartan->id, $this->express->id, [$this->guanambi->id], CarbonImmutable::now()));
    }

    public function test_saas_inspection_in_read_only_mode_cannot_write_hours(): void
    {
        $role = $this->role($this->gartan, 'Gestor da Roupas', ['account.institutional.view', 'account.institutional.update']);
        $ana = $this->operator($this->gartan, [[$role, AccessNode::company($this->gartan->id, $this->roupas->id)]]);
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $start = '/saas/contas/'.$this->gartan->id.'/inspecao';

        $this->actingAs($platform)->postJson($start, ['user_id' => $ana->id, 'mode' => 'read-only'])->assertOk();
        $this->getJson($this->companyUrl())->assertOk();
        $this->saveCompanyHours()->assertForbidden();
        $this->exception($this->companyUrl('', '/excecoes'), ['kind' => 'holiday', 'starts_on' => '2026-06-10'])->assertForbidden();
        $this->patchJson($this->unitUrl($this->guanambi, '/fuso'), ['timezone' => 'America/Manaus'])->assertForbidden();
        $this->assertSame(0, ProfileSection::count() + HourException::count());
        $this->assertSame('America/Sao_Paulo', $this->guanambi->fresh()->timezone);
    }

    public function test_the_overview_payload_shows_the_effective_state_and_the_inherited_exceptions(): void
    {
        $this->saveCompanyHours()->assertNoContent();
        $this->exception($this->companyUrl('', '/excecoes'), ['kind' => 'holiday', 'starts_on' => '2099-12-25', 'note' => 'Natal'])->assertNoContent();
        $this->saveUnitHours($this->guanambi, 'override', $this->standardWeek())->assertNoContent();
        $this->exception($this->unitUrl($this->guanambi, '/excecoes'), ['kind' => 'holiday', 'starts_on' => '2099-12-26'])->assertNoContent();

        // Quem tem horário próprio não recebe a exceção da empresa; quem herda recebe e a vê como herdada.
        $own = $this->getJson($this->unitUrl($this->guanambi))->assertOk();
        $own->assertJsonPath('hours.timezone', 'America/Sao_Paulo')->assertJsonPath('hours.inherited_exceptions', []);
        $this->assertCount(1, $own->json('hours.exceptions'));
        $this->assertNotEmpty($own->json('hours.timezones'));
        $this->assertContains('America/Sao_Paulo', array_column($own->json('hours.timezones'), 'key'));
        $this->assertContains('America/New_York', array_column($own->json('hours.timezones'), 'key'));

        $inheriting = $this->getJson($this->unitUrl($this->caetite))->assertOk();
        $this->assertSame(['Natal'], array_column($inheriting->json('hours.inherited_exceptions'), 'note'));
        $this->assertSame([], $inheriting->json('hours.exceptions'));

        $company = $this->getJson($this->companyUrl())->assertOk();
        $this->assertCount(3, $company->json('hours.units'));
        $this->assertSame(['Natal'], array_column($company->json('hours.exceptions'), 'note'));
    }

    public function test_the_hours_resolver_runs_a_fixed_number_of_queries_for_many_units(): void
    {
        $this->saveCompanyHours()->assertNoContent();

        foreach (range(1, 25) as $number) {
            $unit = $this->unit($this->roupas, 'Loja '.$number);

            if ($number % 3 === 0) {
                $this->saveUnitHours($unit, 'override', $this->standardWeek())->assertNoContent();
            }
        }

        $this->exception($this->companyUrl('', '/excecoes'), ['kind' => 'holiday', 'starts_on' => '2026-06-10'])->assertNoContent();

        $resolver = $this->hours();
        DB::enableQueryLog();
        $all = $resolver->forCompany($this->gartan->id, $this->roupas->id, null, CarbonImmutable::parse('2026-06-10 09:00', 'America/Sao_Paulo'));
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(28, $all);
        $this->assertSame(3, $queries, 'Unidades, seções e exceções: uma consulta de cada, sem N+1.');
    }

    public function test_exceptions_are_read_only_by_the_hours_queries_and_hours_are_resolved_only_through_the_resolvers(): void
    {
        $allowed = [
            'app/Domains/Institucional/Domain/Models/HourException.php',
            'app/Domains/Institucional/Application/Queries/Hours/',
            'app/Domains/Institucional/Application/Actions/Hours/',
            'app/Domains/Colaboradores/Application/Queries/Violations/ListInstitutionalViolationsQuery.php',
        ];
        $violations = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('app'))) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace(base_path().'/', '', $file->getPathname());

            if (! preg_match('/hour_exceptions|\bHourException::/', (string) file_get_contents($file->getPathname()))) {
                continue;
            }

            foreach ($allowed as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    continue 2;
                }
            }

            $violations[] = $path;
        }

        $this->assertSame([], $violations, 'Leitura de hour_exceptions fora das Queries de horários.');

        // O horário semanal só é lido pelo `ProfileResolver`: nenhum código de horários lê `profile_sections`.
        foreach (glob(base_path('app/Domains/Institucional/Application/{Context,Queries/Hours,Actions/Hours}/*.php'), GLOB_BRACE) as $file) {
            if (str_ends_with($file, 'ProfileResolver.php')) {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression('/profile_sections|ProfileSection::/', (string) file_get_contents($file), basename($file));
        }
    }

    public function test_writing_the_company_hours_invalidates_the_unit_memory_of_the_same_request(): void
    {
        $this->saveCompanyHours()->assertNoContent();
        $resolver = $this->hours();
        $owner = ProfileOwner::establishment($this->gartan->id, $this->roupas->id, $this->guanambi->id);
        $instant = CarbonImmutable::parse('2026-06-10 09:00', 'America/Sao_Paulo');
        $this->assertSame(OpenStatus::Open, $resolver->forUnit($owner, $instant)->status);

        // Gravar a empresa na mesma instância (como numa requisição longa) não pode deixar a unidade com a cópia antiga.
        $closed = array_map(fn (): array => ['kind' => 'closed', 'shifts' => ''], $this->standardWeek());
        $this->saveCompanyHours($closed)->assertNoContent();
        $profiles = app(ProfileResolver::class);
        $profiles->forget(ProfileOwner::company($this->gartan->id, $this->roupas->id));

        $this->assertSame(OpenStatus::Closed, $resolver->forUnit($owner, $instant)->status);
    }
}
