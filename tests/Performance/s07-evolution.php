<?php

// S07: massa de lotações e leitura de situação para provar a evolução do banco. Só roda no laboratório s07.
// Uso: php s07-evolution.php seed | status
use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use App\Domains\Colaboradores\Application\Data\Employees\ListEmployeesData;
use App\Domains\Colaboradores\Application\Queries\Employees\ListEmployeesQuery;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

if (getenv('DB_DATABASE') !== 's07') {
    throw new RuntimeException('Somente o laboratório S07.');
}

$user = User::where('email', 'admin@s07.test')->first();

if ($argv[1] === 'seed') {
    Auth::login($user);
    $app->make(AccountContext::class)->enter($user, 'grupo-gartan');
    $call = function (string $path, array $body) use ($app, $user): int {
        Auth::login($user);
        $request = Request::create($path, 'POST', $body, [], [], ['HTTP_ACCEPT' => 'application/json']);
        $request->setUserResolver(fn () => $user);

        return $app->handle($request)->getStatusCode();
    };

    echo 'pessoa: ', $call('/contas/grupo-gartan/colaboradores', [
        'name' => 'Maria Evolução', 'person_type' => 'individual', 'document' => '52998224725', 'companies' => ['gartan-roupas'],
    ]), PHP_EOL;

    $person = DB::table('people')->where('document', '52998224725')->value('id');
    $link = DB::table('employees')->where('person_id', $person)->value('id');
    $base = "/contas/grupo-gartan/colaboradores/$person/vinculos/$link/lotacoes";

    foreach ([['gartan-roupas-guanambi', '2026-01-01', '2026-06-30'], ['gartan-roupas-guanambi', '2026-07-01', ''], ['gartan-roupas-caetite', '2099-01-01', '']] as [$unit, $from, $to]) {
        echo "lotação $unit $from: ", $call($base, ['establishment' => $unit, 'starts_on' => $from, 'ends_on' => $to]), PHP_EOL;
    }

    exit;
}

// status: situação de cada lotação, no SQL puro (fuso fixo de São Paulo, o padrão da S05) e pelo código atual.
$sql = DB::select("select a.id, case when a.starts_on > (now() at time zone 'America/Sao_Paulo')::date then 'future'
    when a.ends_on is not null and a.ends_on < (now() at time zone 'America/Sao_Paulo')::date then 'ended' else 'current' end as status
    from employee_assignments a order by a.id");
echo 'sql: ', implode(',', array_map(fn ($row) => $row->id.'='.$row->status, $sql)), PHP_EOL;

if (DB::getSchemaBuilder()->hasColumn('establishments', 'timezone')) {
    $accountId = (int) DB::table('clients')->where('code', 'grupo-gartan')->value('id');
    $nodes = new AuthorizedNodes(true, [], []);
    $app = app();
    $query = $app->make(ListEmployeesQuery::class);
    $out = [];

    foreach (['current', 'future', 'ended'] as $status) {
        $filters = ListEmployeesData::fromInput(['assignment' => $status], true);
        $out[$status] = $query->execute($accountId, $nodes, $filters, true, true, '/x')->total();
    }

    echo 'app: ', json_encode($out), PHP_EOL;
}
