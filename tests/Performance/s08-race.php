<?php

// S08: cliente de concorrência da biblioteca de mídia. Cada chamada é um processo PHP independente.
// Uso: php s08-race.php <cenário> <argumentos>. Só roda no laboratório s08.
use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Midia\Application\Actions\DeleteMediaAction;
use App\Domains\Midia\Application\Actions\UpdateMediaAction;
use App\Domains\Midia\Application\Actions\UploadMediaAction;
use App\Domains\Midia\Application\Data\UpdateMediaData;
use App\Domains\Midia\Application\Data\UploadMediaData;
use App\Domains\Midia\Application\Queries\FindMediaQuery;
use App\Domains\Midia\Domain\ValueObjects\MediaOwner;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (getenv('DB_DATABASE') !== 's08') {
    throw new RuntimeException('Somente o laboratório S08.');
}

[$scenario, $who, $target] = [$argv[1], $argv[2], $argv[3] ?? 'empresa'];
$user = User::where('email', $who.'@s08.test')->first();
Auth::login($user);
$account = $who === 'b' ? 'grupo-b' : 'grupo-gartan';
$app->make(AccountContext::class)->enter($user, $account);
$company = Company::where('code', $who === 'b' ? 'empresa-b' : 'gartan-roupas')->first();
$owner = $target === 'empresa'
    ? MediaOwner::company($company->account_id, $company->id)
    : MediaOwner::establishment($company->account_id, $company->id, Establishment::where('code', 'like', '%'.$target)->where('company_id', $company->id)->value('id'));

$start = hrtime(true);

try {
    switch ($scenario) {
        // upload <quem> <alvo> <arquivo> [propósito]
        case 'upload':
            $file = new UploadedFile($argv[4], basename($argv[4]), null, null, true);
            $media = app(UploadMediaAction::class)->execute(UploadMediaData::fromInput(['purpose' => $argv[5] ?? 'gallery'], $file), $owner);
            echo 'OK id=', $media->id, ' ', (is_string($media->status) ? $media->status : $media->status->value);
            break;
            // update <quem> <alvo> <id> <alt>
        case 'update':
            $media = app(FindMediaQuery::class)->execute($owner, (int) $argv[4]) ?? throw new RuntimeException('404');
            app(UpdateMediaAction::class)->execute(UpdateMediaData::fromInput(['alt_text' => $argv[5], 'license_note' => null]), $media);
            echo 'OK editada';
            break;
            // delete <quem> <alvo> <id>
        case 'delete':
            $media = app(FindMediaQuery::class)->execute($owner, (int) $argv[4]) ?? throw new RuntimeException('404');
            echo 'OK delete=', var_export(app(DeleteMediaAction::class)->execute($media), true);
            break;
    }
} catch (Throwable $e) {
    echo 'ERRO ', class_basename($e), ': ', substr($e->getMessage(), 0, 80);
}

printf(" %dms pico=%.1fMB\n", (hrtime(true) - $start) / 1e6, memory_get_peak_usage(true) / 1048576);
