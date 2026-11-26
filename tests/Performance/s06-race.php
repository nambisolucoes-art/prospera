<?php

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Institucional\Application\Actions\Profile\CreateProfileSectionAction;
use App\Domains\Institucional\Application\Actions\Profile\PublishProfileSectionsAction;
use App\Domains\Institucional\Application\Actions\Profile\UpdateProfileSectionAction;
use App\Domains\Institucional\Application\Data\Profile\CreateProfileSectionData;
use App\Domains\Institucional\Application\Data\Profile\PublishProfileSectionsData;
use App\Domains\Institucional\Application\Data\Profile\UpdateProfileSectionData;
use App\Domains\Institucional\Application\Queries\Profile\FindProfileSectionQuery;
use App\Domains\Institucional\Application\Queries\Profile\ProfileImpactQuery;
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

if (getenv('DB_DATABASE') !== 's04') {
    throw new RuntimeException('Somente o laboratório S04.');
}

// Uso: php s06-race.php save|publish <empresa|codigo-da-unidade> <secao> <titulo>
[$scenario, $target, $section, $title] = [$argv[1], $argv[2], $argv[3], $argv[4] ?? ''];

$user = User::where('email', 'admin@s04.test')->first();
Auth::login($user);
$app->make(AccountContext::class)->enter($user, 'grupo-gartan');

$company = Company::where('code', 'gartan-roupas')->first();
$owner = $target === 'empresa'
    ? ProfileOwner::company($company->account_id, $company->id)
    : ProfileOwner::establishment(
        $company->account_id,
        $company->id,
        Establishment::where('code', $target)->first()->id,
    );

try {
    if ($scenario === 'save') {
        $saveSection($section, ['mode' => 'override', 'value' => ['title' => $title]], $owner);
    } else {
        $impact = app(ProfileImpactQuery::class)
            ->execute($owner, [ProfileSectionType::from($section)]);
        $data = PublishProfileSectionsData::fromInput([
            'sections' => [$section], 'inheriting' => $impact->inheriting, 'overriding' => $impact->overriding,
        ]);
        app(PublishProfileSectionsAction::class)->execute($data, $owner);
    }

    echo "OK\n";
} catch (Throwable $e) {
    echo get_class($e), ': ', substr($e->getMessage(), 0, 90), "\n";
}
