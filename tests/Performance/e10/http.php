<?php

// Núcleo dos clientes E10: HTTP real (login, CSRF e cookies), leitura independente do PostgreSQL e Mailpit.
// Não há atalho de autenticação: toda sessão nasce do POST /login do Fortify.
use Database\Seeders\Support\FiscalDocuments;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Crypt;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (getenv('DB_DATABASE') !== 'e10') {
    throw new RuntimeException('Somente o laboratório E10.');
}

const E10_PASSWORD = 'Sintetica-E10-senha';
const E10_STATE = '/tmp/e10-state.json';

$GLOBALS['e10_results'] = [];

final class Browser
{
    /** @var array<string,string> */
    public array $cookies = [];

    public function __construct(public string $label) {}

    public function xsrf(): string
    {
        if (! isset($this->cookies['XSRF-TOKEN'])) {
            $this->call('GET', '/login');
        }

        $raw = urldecode(substr($this->cookies['XSRF-TOKEN'], strlen('XSRF-TOKEN=')));

        return CookieValuePrefix::remove(Crypt::decryptString($raw));
    }

    /**
     * @param  array<string,mixed>|null  $input
     * @return array{status:int, body:mixed, metrics:array<string,mixed>, location:?string, raw:string}
     */
    public function call(string $method, string $path, ?array $input = null, bool $json = true): array
    {
        // O token vem antes dos cookies: numa sessão nova, obtê-lo cria a própria sessão.
        if ($method !== 'GET') {
            $input ??= [];
            $input['_token'] ??= $this->xsrfOrEmpty();
        }

        $header = ['Cookie: '.implode('; ', $this->cookies)];

        if ($json) {
            $header[] = 'Accept: application/json';
        } else {
            $header[] = 'Accept: text/html';
        }

        $options = ['ignore_errors' => true, 'follow_location' => 0, 'method' => $method, 'header' => $header];

        if ($method !== 'GET') {
            $options['header'][] = 'Content-Type: application/json';
            $options['header'][] = 'X-CSRF-TOKEN: '.$input['_token'];
            $options['content'] = json_encode($input, JSON_THROW_ON_ERROR);
        }

        $base = getenv('HARNESS_URL') ?: 'http://127.0.0.1:8000';
        $start = hrtime(true);
        $raw = (string) file_get_contents($base.$path, false, stream_context_create(['http' => $options]));
        $elapsed = (hrtime(true) - $start) / 1e6;
        $headers = http_get_last_response_headers() ?? [];
        $status = (int) (explode(' ', $headers[0] ?? '')[1] ?? 0);
        $metrics = ['http_ms' => $elapsed];
        $location = null;

        foreach ($headers as $line) {
            if (preg_match('/^set-cookie: (([^=]+)=[^;]*)/i', $line, $matches)) {
                $this->cookies[$matches[2]] = $matches[1];
            }

            if (str_starts_with(strtolower($line), 'x-e03-metrics:')) {
                $metrics = [...json_decode(trim(explode(':', $line, 2)[1]), true), 'http_ms' => $elapsed];
            }

            if (str_starts_with(strtolower($line), 'location:')) {
                $location = trim(explode(':', $line, 2)[1]);
            }
        }

        return [
            'status' => $status,
            'body' => json_decode($raw, true),
            'metrics' => $metrics,
            'location' => $location,
            'raw' => $raw,
        ];
    }

    private function xsrfOrEmpty(): string
    {
        return $this->xsrf();
    }
}

function login(string $email, string $password = E10_PASSWORD, string $label = ''): Browser
{
    $browser = new Browser($label === '' ? $email : $label);
    $browser->call('GET', '/login');
    $response = $browser->call('POST', '/login', ['email' => $email, 'password' => $password]);

    // O limitador real de login continua ativo: aguarda a janela e registra a ocorrência.
    if ($response['status'] === 429) {
        $GLOBALS['e10_throttled'] = ($GLOBALS['e10_throttled'] ?? 0) + 1;
        sleep(61);
        $response = $browser->call('POST', '/login', ['email' => $email, 'password' => $password]);
    }

    if (! in_array($response['status'], [200, 204, 302], true)) {
        throw new RuntimeException('Login falhou para '.$email.': '.$response['status'].' '.substr($response['raw'], 0, 300));
    }

    return $browser;
}

/** Registra uma verificação. A execução continua para produzir a matriz completa. */
function check(string $journey, string $label, bool $passed, mixed $detail = null): bool
{
    $GLOBALS['e10_results'][] = [
        'journey' => $journey,
        'check' => $label,
        'passed' => $passed,
        'detail' => $detail,
    ];

    if (! $passed) {
        fwrite(STDERR, '[FALHA] '.$journey.': '.$label.' '.json_encode($detail, JSON_UNESCAPED_UNICODE).PHP_EOL);
    }

    return $passed;
}

/** Atalho para conferir o status HTTP com o corpo resumido em caso de divergência. */
function status(string $journey, string $label, array $response, int|array $expected): bool
{
    $passed = in_array($response['status'], (array) $expected, true);
    $detail = ['status' => $response['status']];

    if (! $passed) {
        $detail['body'] = mb_substr($response['raw'], 0, 400);
    }

    return check($journey, $label, $passed, $detail);
}

/** @return array<string,mixed> */
function state(): array
{
    return is_file(E10_STATE) ? json_decode((string) file_get_contents(E10_STATE), true) : [];
}

function remember(string $key, mixed $value): void
{
    $state = state();
    $state[$key] = $value;
    file_put_contents(E10_STATE, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/** Mensagens entregues ao Mailpit isolado para o destinatário, da mais recente para a mais antiga. */
function mailbox(string $address): array
{
    $url = 'http://mail:8025/api/v1/search?query='.rawurlencode('to:"'.$address.'"');
    $result = json_decode((string) file_get_contents($url), true);

    return $result['messages'] ?? [];
}

function mail_body(string $id): string
{
    $message = json_decode((string) file_get_contents('http://mail:8025/api/v1/message/'.$id), true);

    return (string) ($message['Text'] ?? '').(string) ($message['HTML'] ?? '');
}

/** Primeiro link do e-mail mais recente que corresponde ao padrão. */
function mail_link(string $address, string $pattern): ?string
{
    foreach (mailbox($address) as $message) {
        if (preg_match($pattern, html_entity_decode(mail_body($message['ID'])), $matches)) {
            return $matches[0];
        }
    }

    return null;
}

function finish(string $name): void
{
    $results = $GLOBALS['e10_results'];
    $failed = array_values(array_filter($results, fn (array $result): bool => ! $result['passed']));
    $summary = [
        'e10' => $name,
        'checks' => count($results),
        'failed' => count($failed),
        'login_throttled' => $GLOBALS['e10_throttled'] ?? 0,
        'results' => $results,
    ];

    @mkdir('/tmp/e10-results');
    file_put_contents(
        '/tmp/e10-results/'.$name.'.json',
        json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    );

    echo json_encode(['e10' => $name, 'checks' => count($results), 'failed' => count($failed)]), PHP_EOL;

    exit($failed === [] ? 0 : 1);
}

/** CNPJ com dígitos verificadores; raiz e ordem podem ter letras (IN RFB 2.229/2024). */
function cnpj(string $root, string $order = '0001'): string
{
    $base = strtoupper($root.$order);

    foreach ([[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]] as $weights) {
        $sum = 0;

        foreach ($weights as $index => $weight) {
            $sum += (ord($base[$index]) - 48) * $weight;
        }

        $rest = $sum % 11;
        $base .= (string) ($rest < 2 ? 0 : 11 - $rest);
    }

    return $base;
}

function cpf(string $base): string
{
    return FiscalDocuments::cpf($base);
}

/** Envia o diretório de clientes atualizado para a cópia do laboratório. */
function run_id(): string
{
    return substr(bin2hex(random_bytes(3)), 0, 6);
}
