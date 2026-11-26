<?php

// S07: cliente de concorrência. Cada chamada é um processo PHP independente com a própria conexão ao PostgreSQL.
// Uso: php s07-race.php <cenário> <argumentos...>. Só roda no laboratório s07.
use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Institucional\Application\Actions\Hours\CreateHourExceptionAction;
use App\Domains\Institucional\Application\Actions\Hours\SetEstablishmentTimezoneAction;
use App\Domains\Institucional\Application\Actions\Profile\CreateProfileSectionAction;
use App\Domains\Institucional\Application\Actions\Profile\UpdateProfileSectionAction;
use App\Domains\Institucional\Application\Data\Hours\CreateHourExceptionData;
use App\Domains\Institucional\Application\Data\Hours\SetEstablishmentTimezoneData;
use App\Domains\Institucional\Application\Data\Profile\CreateProfileSectionData;
use App\Domains\Institucional\Application\Data\Profile\UpdateProfileSectionData;
use App\Domains\Institucional\Application\Queries\Hours\HourExceptionImpactQuery;
use App\Domains\Institucional\Application\Queries\Profile\FindProfileSectionQuery;
use App\Domains\Institucional\Domain\Enums\HourExceptionKind;
use App\Domains\Institucional\Domain\Enums\ProfileSectionType;
use App\Domains\Institucional\Domain\ValueObjects\ProfileOwner;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;

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

$company = Company::where('code', 'gartan-roupas')->first();
$unit = fn (string $suffix) => Establishment::where('code', 'like', '%'.$suffix)->where('company_id', $company->id)->first();
$companyOwner = ProfileOwner::company($company->account_id, $company->id);
$unitOwner = fn (string $suffix) => ProfileOwner::establishment($company->account_id, $company->id, $unit($suffix)->id);

[$scenario] = [$argv[1]];
$week = fn (string $start) => ['days' => ['mon' => ['kind' => 'open', 'shifts' => [['start' => $start, 'end' => '18:00']]]]];

try {
    switch ($scenario) {
        // hours-save <empresa|sufixo-da-unidade> <hora-inicial>: grava o horário semanal.
        case 'hours-save':
            $owner = $argv[2] === 'empresa' ? $companyOwner : $unitOwner($argv[2]);
            $saveSection('hours_weekly', ['mode' => 'override', 'value' => $week($argv[3])], $owner);
            break;

            // hours-mode <sufixo> <inherit|disabled>: muda a herança da unidade.
        case 'hours-mode':
            $owner = $unitOwner($argv[2]);
            $saveSection('hours_weekly', ['mode' => $argv[3]], $owner);
            break;

            // exception <empresa|sufixo> <início> <fim> [applies]: cadastra a exceção; com `applies`, usa o resumo vigente.
        case 'exception':
            $owner = $argv[2] === 'empresa' ? $companyOwner : $unitOwner($argv[2]);
            $input = ['kind' => 'holiday', 'starts_on' => $argv[3], 'ends_on' => $argv[4]];

            if (($argv[5] ?? '') === 'applies') {
                $input['applies_to_overrides'] = true;
                $input['impact_token'] = app(HourExceptionImpactQuery::class)->execute($owner, HourExceptionKind::Holiday, $argv[3], $argv[4])->token;
            }

            $data = CreateHourExceptionData::fromInput($input);
            app(CreateHourExceptionAction::class)->execute($data, $owner);
            break;

            // timezone <sufixo> <fuso>
        case 'timezone':
            app(SetEstablishmentTimezoneAction::class)->execute(SetEstablishmentTimezoneData::fromInput(['timezone' => $argv[3]]), $unitOwner($argv[2]));
            break;

            // read-after-impact <início> <fim>: lê o resumo e grava com ele depois de uma pausa, para a corrida com a herança.
        case 'stale-exception':
            $owner = $companyOwner;
            $token = app(HourExceptionImpactQuery::class)->execute($owner, HourExceptionKind::Holiday, $argv[2], $argv[3])->token;
            usleep((int) ($argv[4] ?? 300) * 1000);
            $data = CreateHourExceptionData::fromInput([
                'kind' => 'holiday', 'starts_on' => $argv[2], 'ends_on' => $argv[3], 'applies_to_overrides' => true, 'impact_token' => $token,
            ]);
            app(CreateHourExceptionAction::class)->execute($data, $owner);
            break;

        default:
            throw new InvalidArgumentException('cenário desconhecido');
    }

    echo "OK\n";
} catch (Throwable $e) {
    echo get_class($e), ': ', substr($e->getMessage(), 0, 100), "\n";
}
