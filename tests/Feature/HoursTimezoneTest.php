<?php

namespace Tests\Feature;

use App\Domains\Auditoria\Domain\Models\AuditRecord;
use App\Domains\Colaboradores\Domain\Models\Employee;
use App\Domains\Colaboradores\Domain\Models\EmployeeAssignment;
use App\Domains\Institucional\Application\Context\HoursResolver;
use App\Domains\Institucional\Domain\Enums\OpenStatus;
use App\Domains\Institucional\Domain\Models\HourException;
use App\Domains\Institucional\Domain\ValueObjects\Hours\EffectiveHours;
use App\Domains\Institucional\Domain\ValueObjects\ProfileOwner;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use App\Support\BusinessDate;
use App\Support\IanaTimezone;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Concerns\BuildsCommercialScenario;
use Tests\TestCase;

/**
 * S07 do plano 002: fuso IANA por estabelecimento, data civil, horário de verão e a vigência das lotações da S05 sobre o
 * "hoje" de cada unidade. O relógio é sempre controlado: nenhum teste depende da hora da máquina.
 */
class HoursTimezoneTest extends TestCase
{
    use BuildsCommercialScenario;
    use RefreshDatabase;

    private Client $gartan;

    private Company $roupas;

    private Establishment $sede;

    private Establishment $manaus;

    private Establishment $newYork;

    private User $owner;

    private Person $maria;

    private Employee $link;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gartan = $this->account();
        $this->roupas = $this->company($this->gartan, 'Gartan Roupas', '11222333');
        $this->sede = $this->roupas->primaryEstablishment()->sole();
        $this->manaus = $this->unit($this->roupas, 'Manaus');
        $this->newYork = $this->unit($this->roupas, 'Nova York');
        $this->owner = $this->administrator($this->gartan);
        $this->actingAs($this->owner);

        $this->patchJson($this->unitUrl($this->manaus, '/fuso'), ['timezone' => 'America/Manaus'])->assertNoContent();
        $this->patchJson($this->unitUrl($this->newYork, '/fuso'), ['timezone' => 'America/New_York'])->assertNoContent();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function unitUrl(Establishment $unit, string $suffix = ''): string
    {
        return '/conta/estabelecimento/'.$unit->code.'/institucional'.$suffix;
    }

    private function companyUrl(string $suffix = ''): string
    {
        return '/conta/empresa/'.$this->roupas->code.'/institucional'.$suffix;
    }

    private function resolver(): HoursResolver
    {
        $this->app->forgetScopedInstances();

        return app(HoursResolver::class);
    }

    /** @return array<int, EffectiveHours> */
    private function everywhere(string $utcInstant): array
    {
        return $this->resolver()->forCompany($this->gartan->id, $this->roupas->id, null, CarbonImmutable::parse($utcInstant, 'UTC'));
    }

    private function weekdays(string $start, string $end): void
    {
        $day = ['kind' => 'open', 'shifts' => [['start' => $start, 'end' => $end]]];
        $this->patchJson($this->companyUrl('/secoes/hours_weekly'), ['mode' => 'override', 'value' => ['days' => [
            'mon' => $day, 'tue' => $day, 'wed' => $day, 'thu' => $day, 'fri' => $day,
        ]]])->assertNoContent();
    }

    private function hire(): void
    {
        $this->postJson('/conta/colaboradores', [
            'name' => 'Maria Souza', 'person_type' => 'individual', 'document' => '52998224725',
            'companies' => [$this->roupas->code],
        ])->assertNoContent();
        $this->maria = Person::where('document', '52998224725')->sole();
        $this->link = Employee::where('company_id', $this->roupas->id)->sole();
    }

    private function assign(Establishment $unit, string $start, ?string $end = null): EmployeeAssignment
    {
        $this->postJson('/conta/colaboradores/'.$this->maria->id.'/vinculos/'.$this->link->id.'/lotacoes', [
            'establishment' => $unit->code, 'starts_on' => $start, 'ends_on' => $end,
        ])->assertNoContent();

        return EmployeeAssignment::where('establishment_id', $unit->id)->where('starts_on', $start)->sole();
    }

    private function listed(string $assignment, ?string $unit = null): array
    {
        $query = http_build_query(array_filter(['assignment' => $assignment, 'establishment' => $unit]));

        return array_column($this->getJson('/conta/colaboradores?'.$query)->assertOk()->json('employees.data'), 'name');
    }

    // Fuso -----------------------------------------------------------------------------------------------------------

    public function test_the_timezone_defaults_to_sao_paulo_is_iana_only_and_audited_per_unit(): void
    {
        $this->assertSame('America/Sao_Paulo', $this->sede->fresh()->timezone);
        $this->assertSame('America/Manaus', $this->manaus->fresh()->timezone);
        $this->assertSame('America/New_York', $this->newYork->fresh()->timezone);

        foreach (['-03:00', 'UTC-3', 'EST', 'GMT+3', 'Brasilia', 'America/Nowhere', '', 'america/sao_paulo'] as $invalid) {
            $this->patchJson($this->unitUrl($this->sede, '/fuso'), ['timezone' => $invalid])
                ->assertUnprocessable()->assertJsonValidationErrors(['timezone']);
        }
        $this->patchJson($this->unitUrl($this->sede, '/fuso'), [])->assertUnprocessable();
        $this->assertSame('America/Sao_Paulo', $this->sede->fresh()->timezone);

        foreach (['America/Sao_Paulo', 'America/Manaus', 'America/Bahia', 'America/New_York'] as $valid) {
            $this->assertTrue(IanaTimezone::isValid($valid), $valid);
        }

        // A empresa não tem fuso, e o mesmo valor não gera evento.
        $this->patchJson($this->companyUrl('/fuso'), ['timezone' => 'America/Bahia'])->assertNotFound();
        $events = AuditRecord::where('entity_type', 'establishment')->where('establishment_id', $this->manaus->id)->count();
        $this->patchJson($this->unitUrl($this->manaus, '/fuso'), ['timezone' => 'America/Manaus'])->assertNoContent();
        $this->assertSame($events, AuditRecord::where('entity_type', 'establishment')->where('establishment_id', $this->manaus->id)->count());

        $change = AuditRecord::where('entity_type', 'establishment')->where('establishment_id', $this->manaus->id)
            ->where('event', 'updated')->latest('id')->first();
        $this->assertSame($this->roupas->id, $change->company_id);
        $this->assertSame('America/Sao_Paulo', $change->changes['timezone']['before']);
        $this->assertSame('America/Manaus', $change->changes['timezone']['after']);
        $this->assertSame(0, AuditRecord::where('entity_type', 'establishment')->where('establishment_id', $this->sede->id)->where('event', 'updated')->count());
    }

    public function test_the_same_instant_has_a_different_local_date_and_state_in_each_unit(): void
    {
        $this->weekdays('08:00', '18:00');

        // 12:00 UTC de uma quarta: 09:00 em São Paulo, 08:00 em Manaus e 08:00 (horário de verão) em Nova York.
        $june = $this->everywhere('2026-06-10 12:00:00');
        $this->assertSame('2026-06-10', $june[$this->sede->id]->localDate);
        $this->assertSame('09:00', $june[$this->sede->id]->localTime);
        $this->assertSame('08:00', $june[$this->manaus->id]->localTime);
        $this->assertSame('08:00', $june[$this->newYork->id]->localTime);
        $this->assertSame([OpenStatus::Open, OpenStatus::Open, OpenStatus::Open], array_values(array_map(fn ($unit) => $unit->status, $june)));

        // 10:50 UTC: Nova York ainda não abriu (06:50), São Paulo e Manaus também não abriram.
        $early = $this->everywhere('2026-06-10 10:50:00');
        $this->assertSame(OpenStatus::Closed, $early[$this->sede->id]->status);
        $this->assertSame(OpenStatus::Closed, $early[$this->newYork->id]->status);

        // 21:30 UTC: São Paulo (18:30) já fechou, Manaus (17:30) e Nova York (17:30) ainda atendem.
        $late = $this->everywhere('2026-06-10 21:30:00');
        $this->assertSame(OpenStatus::Closed, $late[$this->sede->id]->status);
        $this->assertSame(OpenStatus::Open, $late[$this->manaus->id]->status);
        $this->assertSame(OpenStatus::Open, $late[$this->newYork->id]->status);

        // O horário de trabalho é local: 08:00 locais não é um instante UTC fixo.
        $this->assertSame(OpenStatus::Open, $this->everywhere('2026-06-10 11:00:00')[$this->sede->id]->status);
        $this->assertSame(OpenStatus::Closed, $this->everywhere('2026-06-10 11:00:00')[$this->manaus->id]->status);
    }

    public function test_the_local_date_can_differ_from_the_utc_date_and_exceptions_follow_the_unit_date(): void
    {
        $this->weekdays('08:00', '18:00');
        $this->postJson($this->companyUrl('/excecoes'), ['kind' => 'holiday', 'starts_on' => '2026-06-10'])->assertNoContent();

        // 03:30 UTC do dia 11: São Paulo já está em 11 (00:30) e Manaus ainda em 10 (23:30).
        $edge = $this->everywhere('2026-06-11 03:30:00');
        $this->assertSame('2026-06-11', $edge[$this->sede->id]->localDate);
        $this->assertSame('2026-06-10', $edge[$this->manaus->id]->localDate);
        $this->assertSame('2026-06-10', $edge[$this->newYork->id]->localDate);
        $this->assertNull($edge[$this->sede->id]->exception);
        $this->assertSame('holiday', $edge[$this->manaus->id]->exception['kind']);
        $this->assertSame('holiday', $edge[$this->newYork->id]->exception['kind']);

        // O primeiro dia entra e o seguinte sai à meia-noite local, não à meia-noite UTC.
        $this->assertNull($this->everywhere('2026-06-10 02:59:00')[$this->sede->id]->exception);
        $this->assertSame('holiday', $this->everywhere('2026-06-10 03:00:00')[$this->sede->id]->exception['kind']);
        $this->assertSame('holiday', $this->everywhere('2026-06-11 02:59:00')[$this->sede->id]->exception['kind']);
        $this->assertNull($this->everywhere('2026-06-11 03:00:00')[$this->sede->id]->exception);
    }

    public function test_changing_the_timezone_changes_the_reading_and_never_the_stored_dates(): void
    {
        $this->weekdays('08:00', '18:00');
        $this->postJson($this->companyUrl('/excecoes'), ['kind' => 'holiday', 'starts_on' => '2026-06-10', 'ends_on' => '2026-06-11'])->assertNoContent();
        $this->hire();
        $assignment = $this->assign($this->sede, '2026-06-10', '2026-06-20');
        $stored = [$assignment->getRawOriginal('starts_on'), $assignment->getRawOriginal('ends_on')];
        $exception = HourException::sole();
        $dates = [$exception->getRawOriginal('starts_on'), $exception->getRawOriginal('ends_on')];

        $before = $this->everywhere('2026-06-10 12:00:00')[$this->sede->id];
        $this->patchJson($this->unitUrl($this->sede, '/fuso'), ['timezone' => 'Pacific/Auckland'])->assertNoContent();
        $after = $this->everywhere('2026-06-10 12:00:00')[$this->sede->id];

        $this->assertSame('2026-06-10', $before->localDate);
        $this->assertSame('2026-06-11', $after->localDate);
        $this->assertSame('00:00', $after->localTime);
        $this->assertSame($stored, [$assignment->fresh()->getRawOriginal('starts_on'), $assignment->fresh()->getRawOriginal('ends_on')]);
        $this->assertSame($dates, [$exception->fresh()->getRawOriginal('starts_on'), $exception->fresh()->getRawOriginal('ends_on')]);
    }

    // Horário de verão e virada de dia --------------------------------------------------------------------------------

    public function test_valid_until_is_the_start_of_the_next_local_day_even_on_days_of_23_and_25_hours(): void
    {
        $hours = fn (string $utc, string $zone): array => (function () use ($utc, $zone): array {
            $date = BusinessDate::dateOf(CarbonImmutable::parse($utc, 'UTC'), $zone);
            $start = CarbonImmutable::parse($date.' 00:00:00', $zone);
            $end = BusinessDate::startOfNextDay($date, $zone);

            return [$date, $start->diffInHours($end), $end->utc()->toIso8601String()];
        })();

        // Início do horário de verão em Nova York (08/03/2026): o dia local tem 23 horas.
        [$date, $length, $until] = $hours('2026-03-08 15:00:00', 'America/New_York');
        $this->assertSame('2026-03-08', $date);
        $this->assertEquals(23, $length);
        $this->assertSame('2026-03-09T04:00:00+00:00', $until);

        // Fim do horário de verão (01/11/2026): o dia tem 25 horas.
        [$date, $length, $until] = $hours('2026-11-01 15:00:00', 'America/New_York');
        $this->assertSame('2026-11-01', $date);
        $this->assertEquals(25, $length);
        $this->assertSame('2026-11-02T05:00:00+00:00', $until);

        // Um dia comum tem 24 horas, e o fuso sem horário de verão nunca varia.
        $this->assertEquals(24, $hours('2026-06-10 15:00:00', 'America/New_York')[1]);
        $this->assertEquals(24, $hours('2026-03-08 15:00:00', 'America/Manaus')[1]);

        // Havana muda o relógio à meia-noite (08/03/2026): a meia-noite não existe e o dia seguinte começa à 01:00 local.
        $this->assertSame('2026-03-08T05:00:00+00:00', BusinessDate::startOfNextDay('2026-03-07', 'America/Havana')->utc()->toIso8601String());
        $this->assertSame('2026-03-08 01:00', BusinessDate::startOfNextDay('2026-03-07', 'America/Havana')->format('Y-m-d H:i'));
    }

    public function test_the_response_carries_the_next_local_midnight_as_valid_until(): void
    {
        $this->weekdays('08:00', '18:00');
        $effective = $this->everywhere('2026-03-08 06:00:00');

        // Nova York às 01:00 EST de 08/03: válido até 00:00 EDT de 09/03 (04:00 UTC), 23 horas depois.
        $this->assertSame('2026-03-09T04:00:00+00:00', $effective[$this->newYork->id]->validUntil->utc()->toIso8601String());
        $this->assertSame('2026-03-08T06:00:00+00:00', CarbonImmutable::parse('2026-03-08 06:00:00', 'UTC')->toIso8601String());
        $this->assertSame('2026-03-09T03:00:00+00:00', $effective[$this->sede->id]->validUntil->utc()->toIso8601String());
        $this->assertSame('2026-03-09T04:00:00+00:00', $effective[$this->manaus->id]->validUntil->utc()->toIso8601String());

        // O menor `valid_until` de um bloco com vários fusos é o de São Paulo.
        $least = min(array_map(fn ($unit) => $unit->validUntil->getTimestamp(), $effective));
        $this->assertSame($effective[$this->sede->id]->validUntil->getTimestamp(), $least);
    }

    public function test_daylight_saving_keeps_the_local_opening_hour_and_moves_the_utc_instant(): void
    {
        $this->weekdays('08:00', '12:00');

        // Segunda 02/03/2026 (EST, UTC-5): 08:00 locais são 13:00 UTC. Segunda 09/03 (EDT, UTC-4): são 12:00 UTC.
        $this->assertSame(OpenStatus::Closed, $this->everywhere('2026-03-02 12:59:00')[$this->newYork->id]->status);
        $this->assertSame(OpenStatus::Open, $this->everywhere('2026-03-02 13:00:00')[$this->newYork->id]->status);
        $this->assertSame(OpenStatus::Closed, $this->everywhere('2026-03-09 11:59:00')[$this->newYork->id]->status);
        $this->assertSame(OpenStatus::Open, $this->everywhere('2026-03-09 12:00:00')[$this->newYork->id]->status);

        // Mesma regra na saída do horário de verão (segunda 02/11/2026, já em EST).
        $this->assertSame(OpenStatus::Closed, $this->everywhere('2026-11-02 12:59:00')[$this->newYork->id]->status);
        $this->assertSame(OpenStatus::Open, $this->everywhere('2026-11-02 13:00:00')[$this->newYork->id]->status);
        $this->assertSame(OpenStatus::Open, $this->everywhere('2026-11-02 16:59:00')[$this->newYork->id]->status);
        $this->assertSame(OpenStatus::Closed, $this->everywhere('2026-11-02 17:00:00')[$this->newYork->id]->status);
    }

    public function test_midnight_boundaries_split_the_days_without_overlap(): void
    {
        $day = ['kind' => 'open', 'shifts' => [['start' => '18:00', 'end' => '24:00']]];
        $night = ['kind' => 'open', 'shifts' => [['start' => '00:00', 'end' => '02:00']]];
        $this->patchJson($this->companyUrl('/secoes/hours_weekly'), ['mode' => 'override', 'value' => ['days' => ['wed' => $day, 'thu' => $night]]])->assertNoContent();
        $local = fn (string $time) => $this->resolver()->forUnit(
            ProfileOwner::establishment($this->gartan->id, $this->roupas->id, $this->sede->id),
            CarbonImmutable::parse($time, 'America/Sao_Paulo'),
        )->status;

        $this->assertSame(OpenStatus::Open, $local('2026-06-10 23:59:59'));
        $this->assertSame(OpenStatus::Open, $local('2026-06-11 00:00:00'));
        $this->assertSame(OpenStatus::Open, $local('2026-06-11 01:59:59'));
        $this->assertSame(OpenStatus::Closed, $local('2026-06-11 02:00:00'));
        $this->assertSame(OpenStatus::Closed, $local('2026-06-10 17:59:59'));
    }

    // Vigência das lotações (S05) -----------------------------------------------------------------------------------

    public function test_a_future_assignment_starts_on_the_local_date_of_its_own_unit(): void
    {
        $this->hire();
        $this->assign($this->sede, '2026-06-11');
        $manaus = $this->assign($this->manaus, '2026-06-11');

        // 03:30 UTC de 11/06: já é dia 11 em São Paulo (00:30) e ainda é dia 10 em Manaus (23:30).
        Carbon::setTestNow(CarbonImmutable::parse('2026-06-11 03:30:00', 'UTC'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-11 03:30:00', 'UTC'));

        $this->assertSame([], $this->listed('current', $this->manaus->code));
        $this->assertSame(['Maria Souza'], $this->listed('future', $this->manaus->code));
        $this->assertSame(['Maria Souza'], $this->listed('current', $this->sede->code));
        $this->assertSame([], $this->listed('future', $this->sede->code));
        $this->assertSame('future', $manaus->fresh()->statusOn(BusinessDate::todayIn('America/Manaus')));

        $status = collect($this->getJson('/conta/colaboradores/'.$this->maria->id.'/editar')->assertOk()->json('links.0.assignments'))
            ->pluck('status', 'establishment_code');
        $this->assertSame('current', $status[$this->sede->code]);
        $this->assertSame('future', $status[$this->manaus->code]);

        // Uma hora depois também é dia 11 em Manaus.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-11 04:00:00', 'UTC'));
        Carbon::setTestNow(CarbonImmutable::parse('2026-06-11 04:00:00', 'UTC'));
        $this->assertSame(['Maria Souza'], $this->listed('current', $this->manaus->code));
    }

    public function test_an_ended_assignment_stops_on_the_local_date_of_its_own_unit(): void
    {
        $this->hire();
        $this->assign($this->sede, '2026-06-01', '2026-06-10');
        $this->assign($this->manaus, '2026-06-01', '2026-06-10');

        Carbon::setTestNow(CarbonImmutable::parse('2026-06-11 03:30:00', 'UTC'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-11 03:30:00', 'UTC'));

        // O último dia vale inteiro: em São Paulo já é 11 (encerrada); em Manaus ainda é 10 (vigente).
        $this->assertSame(['Maria Souza'], $this->listed('ended', $this->sede->code));
        $this->assertSame([], $this->listed('current', $this->sede->code));
        $this->assertSame([], $this->listed('ended', $this->manaus->code));
        $this->assertSame(['Maria Souza'], $this->listed('current', $this->manaus->code));
    }

    public function test_inactivating_the_employee_ends_each_assignment_on_the_local_date_of_its_unit(): void
    {
        $this->hire();
        $this->assign($this->sede, '2026-06-01');
        $this->assign($this->manaus, '2026-06-01');

        Carbon::setTestNow(CarbonImmutable::parse('2026-06-11 03:30:00', 'UTC'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-11 03:30:00', 'UTC'));

        $this->patchJson('/conta/colaboradores/'.$this->maria->id.'/vinculos/'.$this->link->id, ['active' => '0'])->assertNoContent();

        $ends = EmployeeAssignment::pluck('ends_on', 'establishment_id');
        $this->assertSame('2026-06-11', substr($ends[$this->sede->id], 0, 10));
        $this->assertSame('2026-06-10', substr($ends[$this->manaus->id], 0, 10));
    }

    public function test_the_assignment_form_default_date_is_the_local_date_of_the_chosen_unit(): void
    {
        $this->hire();
        Carbon::setTestNow(CarbonImmutable::parse('2026-06-11 03:30:00', 'UTC'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-11 03:30:00', 'UTC'));

        $options = $this->getJson('/conta/colaboradores/'.$this->maria->id.'/editar')->assertOk()->json('links.0.assignment_options.establishments');
        $today = array_column($options, 'today', 'code');

        $this->assertSame('2026-06-11', $today[$this->sede->code]);
        $this->assertSame('2026-06-10', $today[$this->manaus->code]);
        $this->assertSame('2026-06-10', $today[$this->newYork->code]);
    }

    public function test_the_migration_gives_every_existing_unit_the_old_default_and_changes_no_assignment_status(): void
    {
        $this->hire();
        $this->assign($this->sede, '2026-06-01', '2026-06-10');
        $this->assign($this->sede, '2026-06-11');
        $this->assign($this->manaus, '2026-06-20');

        Carbon::setTestNow(CarbonImmutable::parse('2026-06-15 12:00:00', 'UTC'));
        $reference = fn (): array => EmployeeAssignment::orderBy('id')->get()
            ->map(fn ($assignment) => $assignment->statusOn(BusinessDate::todayIn('America/Sao_Paulo')))->all();
        $before = $reference();
        $this->assertSame(['ended', 'current', 'future'], $before);

        // Desfaz a coluna e reaplica a migration: tudo volta ao padrão de São Paulo e nenhuma vigência muda.
        $migration = require base_path('database/migrations/2026_11_28_000001_add_timezone_to_establishments.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('establishments', 'timezone'));
        $migration->up();

        $this->assertSame([], DB::table('establishments')->where('timezone', '!=', 'America/Sao_Paulo')->pluck('code')->all());
        $this->assertSame($before, $reference());
        $this->assertSame(0, DB::table('hour_exceptions')->count());
    }

    public function test_the_hours_migrations_roll_back_and_reapply_cleanly(): void
    {
        $this->assertTrue(Schema::hasTable('hour_exceptions'));

        $exceptions = require base_path('database/migrations/2026_11_28_000002_create_hour_exceptions.php');
        $exceptions->down();
        $this->assertFalse(Schema::hasTable('hour_exceptions'));
        $exceptions->up();
        $this->assertTrue(Schema::hasTable('hour_exceptions'));

        $this->assertTrue(Schema::hasColumns('hour_exceptions', [
            'account_id', 'company_id', 'establishment_id', 'kind', 'starts_on', 'ends_on', 'shifts', 'note', 'applies_to_overrides',
        ]));
    }

    public function test_the_database_refuses_incoherent_exceptions(): void
    {
        $base = [
            'account_id' => $this->gartan->id, 'company_id' => $this->roupas->id, 'establishment_id' => null,
            'kind' => 'holiday', 'starts_on' => '2026-06-10', 'ends_on' => '2026-06-10', 'shifts' => null,
            'applies_to_overrides' => false, 'created_at' => now(), 'updated_at' => now(),
        ];
        $refused = function (array $change) use ($base): bool {
            try {
                DB::table('hour_exceptions')->insert([$change + $base]);
            } catch (QueryException) {
                return true;
            }

            return false;
        };

        $this->assertTrue($refused(['kind' => 'party']));
        $this->assertTrue($refused(['ends_on' => '2026-06-09']));
        $this->assertTrue($refused(['kind' => 'special_hours']));
        $this->assertTrue($refused(['shifts' => '[{"start":"08:00","end":"12:00"}]']));
        $this->assertTrue($refused(['establishment_id' => $this->manaus->id, 'applies_to_overrides' => true]));

        // Unidade de outra empresa e conta inexistente.
        $other = $this->company($this->gartan, 'Gartan Express', '44555666');
        $this->assertTrue($refused(['establishment_id' => $other->primaryEstablishment()->sole()->id]));
        $this->assertTrue($refused(['account_id' => 9999]));

        $this->assertFalse($refused(['kind' => 'special_hours', 'shifts' => '[{"start":"08:00","end":"12:00"}]']));
        $this->assertSame(1, DB::table('hour_exceptions')->count());
    }
}
