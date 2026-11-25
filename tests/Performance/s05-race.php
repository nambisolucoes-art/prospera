<?php

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use App\Domains\Colaboradores\Application\Actions\Assignments\CreateEmployeeAssignmentAction;
use App\Domains\Colaboradores\Application\Data\Assignments\CreateEmployeeAssignmentData;
use App\Domains\Colaboradores\Application\Queries\Assignments\ListAssignmentOptionsQuery;
use App\Domains\Colaboradores\Domain\Models\Employee;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (getenv('DB_DATABASE') !== 's04') {
    throw new RuntimeException('lab');
}
[$cenario, $unitCode, $start, $end, $primary] = [$argv[1], $argv[2], $argv[3], $argv[4] === 'null' ? null : $argv[4], $argv[5] === '1'];
$user = User::where('email', 'admin@s04.test')->first();
Auth::login($user);
$account = Client::where('code', 'grupo-gartan')->first();
$app->make(AccountContext::class)->enter($user, 'grupo-gartan');
$employee = Employee::where('account_id', $account->id)->whereHas('person', fn ($q) => $q->where('name', 'Carla Dias'))->first();
$options = app(ListAssignmentOptionsQuery::class)->execute($account->id, $employee->company_id, new AuthorizedNodes(tenant: true));
try {
    $data = CreateEmployeeAssignmentData::fromInput(['establishment' => $unitCode, 'starts_on' => $start, 'ends_on' => $end, 'is_primary' => $primary], $options);
    app(CreateEmployeeAssignmentAction::class)->execute($data, $employee);
    echo "OK\n";
} catch (Throwable $e) {
    echo get_class($e), ': ', substr($e->getMessage(), 0, 90), "\n";
}
