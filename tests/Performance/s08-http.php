<?php

// S08: HTTP real contra FrankenPHP no laboratório s08. Alterna contas, empresas, unidades e permissões com envios válidos
// e inválidos, edições e exclusões. Confere códigos, ausência de vazamento e crescimento de memória do processo do servidor.
// Uso (dentro do laboratório): LAB_PASSWORD=... php s08-http.php [rodadas]
$base = 'http://127.0.0.1:8000';
$password = getenv('LAB_PASSWORD') ?: throw new RuntimeException('Defina LAB_PASSWORD.');
$corpus = '/tmp/corpus';
$rounds = (int) ($argv[1] ?? 20);
$jars = [];

function call(string $user, string $method, string $path, array $fields = [], bool $multipart = false): array
{
    global $base, $jars;
    $jar = $jars[$user] ??= tempnam('/tmp', 'jar');
    $token = '';
    foreach (file($jar, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (str_contains($line, "XSRF-TOKEN\t")) {
            $token = urldecode(substr($line, strrpos($line, "\t") + 1));
        }
    }
    $handle = curl_init($base.$path);
    curl_setopt_array($handle, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-XSRF-TOKEN: '.$token, ...($multipart ? [] : ['Content-Type: application/json'])]]);
    if ($fields !== [] || $method !== 'GET') {
        curl_setopt($handle, CURLOPT_POSTFIELDS, $multipart ? $fields : json_encode($fields));
    }
    $start = hrtime(true);
    $body = (string) curl_exec($handle);
    $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

    return [$code, $body, (hrtime(true) - $start) / 1e6];
}

function login(string $user, string $password): void
{
    call($user, 'GET', '/login');
    [$code] = call($user, 'POST', '/login', ['email' => $user.'@s08.test', 'password' => $password]);
    if ($code >= 400) {
        throw new RuntimeException("login $user: $code");
    }
}

function rss(): int
{
    $total = 0;
    foreach (glob('/proc/[0-9]*/comm') ?: [] as $comm) {
        if (str_contains((string) @file_get_contents($comm), 'frankenphp') || str_contains((string) @file_get_contents($comm), 'php')) {
            $status = (string) @file_get_contents(dirname($comm).'/status');
            if (preg_match('/VmRSS:\s+(\d+)/', $status, $matches) && dirname($comm) !== '/proc/'.getmypid()) {
                $total += (int) $matches[1];
            }
        }
    }

    return intdiv($total, 1024);
}

function upload(string $user, string $path, string $file, string $purpose = 'gallery', string $name = ''): array
{
    return call($user, 'POST', $path, ['file' => new CURLFile($file, '', $name ?: basename($file)), 'purpose' => $purpose, 'alt_text' => 'Texto'], true);
}

foreach (['admin', 'viewer', 'ed', 'gui', 'b'] as $user) {
    login($user, $password);
}
$ga = '/contas/grupo-gartan';
$gb = '/contas/grupo-b';
$companyA = "$ga/empresa/gartan-roupas/midia";
$unitA = "$ga/estabelecimento/gartan-roupas-guanambi/midia";
$unitB = "$ga/estabelecimento/gartan-roupas-caetite/midia";
$companyB = "$gb/empresa/empresa-b/midia";
$failures = [];
$expect = function (string $label, array $result, int|array $codes) use (&$failures): void {
    if (! in_array($result[0], (array) $codes, true)) {
        $failures[] = "$label: HTTP {$result[0]} ".substr($result[1], 0, 120);
    }
    if (preg_match('#(/app/|storage/app|/var/www|Stack trace)#', $result[1])) {
        $failures[] = "$label: resposta revela caminho interno";
    }
};

$before = rss();
$samples = [];
$times = [];
for ($i = 1; $i <= $rounds; $i++) {
    $r = upload('admin', $companyA, "$corpus/jpeg-1600x1200.jpg", 'gallery');
    $times[] = $r[2];
    $expect('admin envia na empresa', $r, [200, 201, 204, 302]);
    $id = null;
    foreach (json_decode(call('admin', 'GET', $companyA)[1], true)['media']['data'] ?? [] as $item) {
        if ($item['scope'] === 'corporate') {
            $id = $item['id'];
            break;
        }
    }
    $expect('unidade A envia', upload('gui', $unitA, "$corpus/png-alpha.png", 'logo'), [200, 201, 204, 302]);
    $expect('visualizador não envia', upload('viewer', $companyA, "$corpus/jpeg-1600x1200.jpg"), 403);
    $expect('unidade não envia na empresa', upload('gui', $companyA, "$corpus/jpeg-1600x1200.jpg"), [403, 404]);
    $expect('unidade A não escreve na B', upload('gui', $unitB, "$corpus/jpeg-1600x1200.jpg"), [403, 404]);
    $expect('conta B não escreve na conta A', upload('b', $companyA, "$corpus/jpeg-1600x1200.jpg"), [403, 404]);
    $expect('conta B envia na própria', upload('b', $companyB, "$corpus/webp-12mp.webp"), [200, 201, 204, 302]);
    $expect('48 MP recusado', upload('admin', $companyA, "$corpus/jpeg-48mp.jpg"), 422);
    $expect('svg recusado', upload('admin', $companyA, "$corpus/svg-com-script.svg", 'gallery', 'a.jpg'), 422);
    $expect('truncado recusado', upload('admin', $companyA, "$corpus/jpeg-truncado.jpg"), 422);
    $expect('gif recusado', upload('admin', $companyA, "$corpus/gif-animado.gif", 'gallery', '../../x.jpg'), 422);
    $list = call('viewer', 'GET', $companyA);
    $expect('viewer lista', $list, 200);
    $expect('b lista a conta A', call('b', 'GET', $companyA), [403, 404]);
    $expect('b lista a própria', call('b', 'GET', $companyB), 200);
    if (str_contains(call('b', 'GET', $companyB)[1], 'gartan')) {
        $failures[] = 'vazamento de dados da conta A na listagem da conta B';
    }
    if ($id !== null) {
        $expect('b edita mídia da A', call('b', 'PATCH', "$companyB/$id", ['alt_text' => 'x']), 404);
        $expect('viewer exclui', call('viewer', 'DELETE', "$companyA/$id"), 403);
        $expect('b baixa original da A', call('b', 'GET', "$companyA/$id/original"), [403, 404]);
        $expect('ed edita', call('ed', 'PATCH', "$companyA/$id", ['alt_text' => 'Novo '.$i]), [200, 204, 302]);
        $expect('ed exclui', call('ed', 'DELETE', "$companyA/$id"), [200, 204, 302]);
    }
    if ($i === 1 || $i % 5 === 0) {
        $samples[$i] = rss();
    }
}
sort($times);
printf("rodadas=%d, requisições≈%d, envio admin: média %.0f ms, p95 %.0f ms, máx %.0f ms\n", $rounds, $rounds * 22, array_sum($times) / count($times), $times[(int) floor(count($times) * 0.95)] ?? end($times), end($times));
printf("RSS dos processos PHP/FrankenPHP: antes %d MB, amostras %s\n", $before, json_encode($samples));
echo $failures === [] ? "RESULTADO: sem falhas de isolamento, permissão ou vazamento\n" : "FALHAS:\n".implode("\n", array_slice(array_unique($failures), 0, 20))."\n";
