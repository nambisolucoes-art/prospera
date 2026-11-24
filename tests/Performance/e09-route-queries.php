<?php

// Somente laboratório E09. Lista o SQL de uma requisição GET como numa requisição nova do worker.
// Uso: php tests/Performance/e09-route-queries.php <caminho> [e-mail]
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require '/app/vendor/autoload.php';

$app = require '/app/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$app->make(ConsoleKernel::class)->bootstrap();

if (getenv('DB_DATABASE') !== 'e09') {
    throw new RuntimeException('Somente laboratório.');
}

$path = $argv[1] ?? '/contas/laboratorio-conta-1/etiquetas';
$user = User::where('email', $argv[2] ?? 'multi@e09.test')->sole();
$log = null;

DB::listen(function (QueryExecuted $query) use (&$log): void {
    if (is_array($log)) {
        $log[] = preg_replace('/\s+/', ' ', $query->sql).' | '.json_encode($query->bindings);
    }
});

// A primeira volta aquece o processo; a segunda é medida, sem as instâncias scoped da anterior.
foreach ([0, 1] as $round) {
    $app->forgetScopedInstances();
    Auth::guard('web')->setUser($user);
    $log = [];
    $request = Request::create($path, 'GET', server: ['HTTP_ACCEPT' => 'application/json']);
    $response = $kernel->handle($request);
}

foreach ($log as $index => $query) {
    echo $index + 1, '. ', substr($query, 0, 260), PHP_EOL;
}

echo 'status ', $response->getStatusCode(), ' queries ', count($log), PHP_EOL;
