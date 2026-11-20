<?php

// Cenários sintéticos da E04, carregados somente no ambiente descartável preparado pelos runners.
use App\Domains\Organizacao\Application\Actions\CreateCompanyAction;
use App\Domains\Organizacao\Application\Actions\SaveEstablishmentAction;
use App\Domains\Organizacao\Application\Data\SaveCompanyData;
use App\Domains\Organizacao\Application\Data\SaveEstablishmentData;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function cnpj(int $root, int $order): string
{
    $base = str_pad((string) $root, 8, '0', STR_PAD_LEFT).str_pad((string) $order, 4, '0', STR_PAD_LEFT);

    foreach ([[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]] as $weights) {
        $sum = 0;

        foreach ($weights as $index => $weight) {
            $sum += (ord($base[$index]) - 48) * $weight;
        }

        $base .= (string) ($sum % 11 < 2 ? 0 : 11 - $sum % 11);
    }

    return $base;
}

$cities = [['Guanambi', 'BA'], ['Caetité', 'BA'], ['Vitória da Conquista', 'BA'], ['Montes Claros', 'MG']];
$scenarios = ['1x1' => [1], '1x3' => [3], '3xN' => [2, 3, 5], '10' => array_fill(0, 10, 1), '50' => array_fill(0, 5, 10)];
$root = 11222000;

foreach ($scenarios as $key => $units) {
    $account = Client::create(['name' => 'Cenário '.$key, 'active' => true]);

    User::factory()->create([
        'name' => 'Administrador '.$key,
        'email' => 'owner-'.strtolower($key).'@e04.test',
        'account_id' => $account->id,
        'is_account_admin' => true,
    ]);

    foreach ($units as $companyIndex => $total) {
        $root++;
        [$city, $state] = $cities[$companyIndex % count($cities)];

        $company = app(CreateCompanyAction::class)->execute($account->id, SaveCompanyData::fromInput([
            'person_type' => 'company',
            'name' => 'Empresa '.($companyIndex + 1).' do cenário '.$key.' Ltda',
            'trade_name' => $key === '1x1' ? 'Gartan 3' : 'Marca '.($companyIndex + 1),
            'document' => cnpj($root, 1),
            'tax_regime' => 'simples-nacional',
            'primary' => [
                'classification' => 'headquarters',
                'address' => ['postal_code' => '46430000', 'city' => $city, 'state' => $state, 'street' => 'Rua Central'],
                'phone' => '7734510000',
            ],
        ], $account->id, null));

        for ($order = 2; $order <= $total; $order++) {
            [$branchCity, $branchState] = $cities[$order % count($cities)];

            app(SaveEstablishmentAction::class)->execute($company, null, SaveEstablishmentData::fromInput([
                'name' => 'Filial '.$order.' '.$branchCity,
                'classification' => $order % 3 === 0 ? 'unregistered' : 'branch',
                'tax_document' => cnpj($root, $order),
                'address' => ['city' => $branchCity, 'state' => $branchState],
                'email' => 'filial'.$order.'@e04.test',
            ], $company, null));
        }
    }
}

DB::statement('ANALYZE');
echo "Cenários da E04 carregados.\n";
