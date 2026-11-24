<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

require __DIR__.'/organization-http.php';
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (getenv('DB_DATABASE') !== 'e09') {
    throw new RuntimeException('Somente laboratório.');
}
function csrf(array $cookies): string
{
    return CookieValuePrefix::remove(Crypt::decryptString(urldecode(substr($cookies['XSRF-TOKEN'], 11))));
}
$cookies = [];
request('/login', $cookies);
request('/login', $cookies, 200, [
    '_token' => csrf($cookies), 'password' => 'password',
    'email' => $argv[1] === 'write' ? 'multi@e09.test' : 'single@e09.test',
]);
$base = '/contas/laboratorio-conta-1';
if ($argv[1] === 'write') {
    request($base.'/etiquetas', $cookies, 200, [
        '_token' => csrf($cookies), 'name' => 'E09 operacao em voo', 'module' => 'customers', 'color' => '#123456',
    ]);
    for ($attempt = 0; $attempt < 100; $attempt++) {
        $status = DB::table('account_memberships')
            ->where('user_id', 1)
            ->where('account_id', 1)
            ->value('status');

        if ($status === 'suspended') {
            break;
        }
        usleep(100000);
    }
    request($base.'/etiquetas', $cookies, 403);
    echo json_encode(['authorized_inflight' => 200, 'next_request' => 403]), PHP_EOL;
} else {
    $operation = $argv[1] === 'restore' ? 'reativar' : 'suspender';
    request($base.'/usuarios/1/'.$operation, $cookies, 200, ['_token' => csrf($cookies)], 'PATCH');
    echo json_encode(['membership_operation' => $operation, 'status' => 200]), PHP_EOL;
}
