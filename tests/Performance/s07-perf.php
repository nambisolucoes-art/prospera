<?php

// S07: medição do resolvedor de horários em PostgreSQL (laboratório s07). Mede consultas SQL, mediana e p95 do tempo e a memória.
// Uso: php s07-perf.php prepare | measure
use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Institucional\Application\Actions\Hours\CreateHourExceptionAction;
use App\Domains\Institucional\Application\Actions\Profile\CreateProfileSectionAction;
use App\Domains\Institucional\Application\Actions\Profile\UpdateProfileSectionAction;
use App\Domains\Institucional\Application\Context\HoursResolver;
use App\Domains\Institucional\Application\Data\Hours\CreateHourExceptionData;
use App\Domains\Institucional\Application\Data\Profile\CreateProfileSectionData;
use App\Domains\Institucional\Application\Data\Profile\UpdateProfileSectionData;
use App\Domains\Institucional\Application\Queries\Hours\HourExceptionImpactQuery;
use App\Domains\Institucional\Application\Queries\Profile\FindProfileSectionQuery;
use App\Domains\Institucional\Domain\Enums\HourExceptionKind;
use App\Domains\Institucional\Domain\Enums\ProfileSectionType;
use App\Domains\Institucional\Domain\ValueObjects\ProfileOwner;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Cria ou altera a seção, como o controller: Create sem linha anterior, Update com ela.
$saveSection = function (string $section, array $input, ProfileOwner $owner): void {
    $existing = app(FindProfileSectionQuery::class)->execute($owner, ProfileSectionType::from($section));

    if ($existing === null) {
        app(CreateProfileSectionAction::class)->execute(CreateProfileSectionData::fromInput($section, $input), $owner);

        return;
    }

    app(UpdateProfileSectionAction::class)->execute(UpdateProfileSectionData::fromInput($section, $input, null), $existing);
};

if (getenv('DB_DATABASE') !== 's07') {
    throw new RuntimeException('Somente o laboratório S07.');
}

$user = User::where('email', 'admin@s07.test')->first();
Auth::login($user);
$app->make(AccountContext::class)->enter($user, 'grupo-gartan');
$redeId = (int) DB::table('companies')->where('code', 'rede-quarenta')->value('id');
$accountId = (int) DB::table('companies')->where('id', $redeId)->value('account_id');
$units = DB::table('establishments')->where('company_id', $redeId)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
$company = ProfileOwner::company($accountId, $redeId);
$week = ['days' => ['mon' => ['kind' => 'open', 'shifts' => [['start' => '08:00', 'end' => '12:00'], ['start' => '14:00', 'end' => '18:00']]]]];

if ($argv[1] === 'prepare') {
    $saveSection('hours_weekly', ['mode' => 'override', 'value' => $week], $company);
    $zones = ['America/Sao_Paulo', 'America/Manaus', 'America/Bahia', 'America/New_York'];

    foreach ($units as $index => $id) {
        DB::table('establishments')->where('id', $id)->update(['timezone' => $zones[$index % 4]]);
        $owner = ProfileOwner::establishment($accountId, $redeId, $id);

        if ($index % 3 === 0) {
            $saveSection('hours_weekly', ['mode' => 'override', 'value' => $week], $owner);
        }

        // Exceções locais em metade das unidades: 3 por unidade, em datas diferentes.
        if ($index % 2 === 0) {
            foreach ([10, 20, 28] as $day) {
                app(CreateHourExceptionAction::class)->execute(CreateHourExceptionData::fromInput(['kind' => 'holiday', 'starts_on' => "2026-12-$day"]), $owner);
            }
        }
    }

    // 24 exceções corporativas (a metade alcança também as unidades com horário próprio).
    foreach (range(1, 24) as $n) {
        $day = sprintf('2026-%02d-%02d', 1 + intdiv($n - 1, 2), 5 + (($n - 1) % 2) * 10);
        $input = ['kind' => 'temporary_closure', 'starts_on' => $day, 'ends_on' => $day];

        if ($n % 2 === 0) {
            $input['applies_to_overrides'] = true;
            $input['impact_token'] = app(HourExceptionImpactQuery::class)->execute($company, HourExceptionKind::TemporaryClosure, $day, $day)->token;
        }

        app(CreateHourExceptionAction::class)->execute(CreateHourExceptionData::fromInput($input), $company);
    }

    echo 'preparado: ', count($units), ' unidades, ', DB::table('hour_exceptions')->where('company_id', $redeId)->count(), ' exceções, ',
    DB::table('profile_sections')->where('company_id', $redeId)->where('section', 'hours_weekly')->count(), " seções\n";
    exit;
}

$measure = function (string $label, callable $call, int $rounds = 200) use ($app): void {
    $times = [];
    DB::enableQueryLog();
    $call();
    $queries = count(DB::getQueryLog());
    DB::flushQueryLog();
    DB::disableQueryLog();

    for ($i = 0; $i < $rounds; $i++) {
        $app->forgetScopedInstances();
        $start = hrtime(true);
        $call();
        $times[] = (hrtime(true) - $start) / 1e6;
    }

    sort($times);
    printf("%-58s consultas=%d  mediana=%.2f ms  p95=%.2f ms  memória=%.1f MB\n", $label, $queries, $times[intdiv($rounds, 2)], $times[(int) floor($rounds * 0.95)], memory_get_peak_usage() / 1048576);
};

$instant = CarbonImmutable::parse('2026-12-10 12:00:00', 'UTC');
$one = $units[0];
$call = fn (?array $ids) => app(HoursResolver::class)->forCompany($accountId, $redeId, $ids, $instant);

$gartan = (int) DB::table('companies')->where('code', 'gartan-roupas')->value('id');
$measure('1 unidade (empresa de 5 unidades, sem exceções)', fn () => app(HoursResolver::class)->forCompany($accountId, $gartan, [(int) DB::table('establishments')->where('company_id', $gartan)->value('id')], $instant));
$measure('1 unidade da rede (40 unidades, 99 exceções)', fn () => $call([$one]));
$measure('10 unidades da rede', fn () => $call(array_slice($units, 0, 10)));
$measure('40 unidades da rede (4 fusos, 14 com horário próprio)', fn () => $call(null));
$measure('resumo de alcance de exceção da empresa (40 unidades)', fn () => app(HourExceptionImpactQuery::class)->execute($company, HourExceptionKind::Holiday, '2026-12-25', '2026-12-25'), 100);

$result = $call(null);
$by = array_count_values(array_map(fn ($hours) => $hours->status->value.'/'.($hours->exception['owner'] ?? 'semana'), $result));
ksort($by);
echo 'distribuição em 10/12/2026 12:00 UTC: ', json_encode($by), PHP_EOL;
