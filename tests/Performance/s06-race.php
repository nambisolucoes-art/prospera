<?php

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Institucional\Application\Actions\Profile\PublishProfileSectionsAction;
use App\Domains\Institucional\Application\Actions\Profile\SaveProfileSectionAction;
use App\Domains\Institucional\Application\Data\Profile\PublishProfileSectionsData;
use App\Domains\Institucional\Application\Data\Profile\SaveProfileSectionData;
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
        $data = SaveProfileSectionData::fromInput($section, ['mode' => 'override', 'value' => ['title' => $title]], $owner->isEstablishment(), null);
        app(SaveProfileSectionAction::class)->execute($data, $owner);
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
