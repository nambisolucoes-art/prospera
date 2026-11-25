<?php

use App\Domains\Acessos\Application\Actions\CreateAccountInvitationAction;
use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (getenv('DB_DATABASE') !== 'e09' || config('mail.default') !== 'array') {
    throw new RuntimeException('Somente laboratório E09 com e-mail em memória.');
}
$first = Client::where('code', 'laboratorio-conta-1')->sole();
$target = Client::where('code', 'laboratorio-conta-2')->sole();
$role = Role::where('account_id', $target->id)->where('name', 'Operador limitado')->sole();
$recipients = [];
foreach (range(1, 10) as $number) {
    $recipients[] = User::factory()->asAccountAdministrator($first->id)->create([
        'email' => 'accept-sample-'.$number.'@e09.test',
    ]);
}
auth()->login(User::where('email', 'five@e09.test')->sole());
app(AccountContext::class)->enter(auth()->user(), $target->code);
$samples = [];
$tokens = [];
foreach ($recipients as $recipient) {
    DB::flushQueryLog();
    DB::enableQueryLog();
    memory_reset_peak_usage();
    $memory = memory_get_usage();
    $start = hrtime(true);
    app(CreateAccountInvitationAction::class)->execute(
        $target, $recipient->email, $role->id, AccessNode::tenant($target->id), null,
    );
    $samples[] = [
        'ms' => (hrtime(true) - $start) / 1e6,
        'queries' => count(DB::getQueryLog()),
        'peak_delta_bytes' => memory_get_peak_usage() - $memory,
    ];
    DB::disableQueryLog();
    DB::flushQueryLog();
    $message = Mail::getSymfonyTransport()->messages()->last()->getOriginalMessage()->getTextBody();
    if (preg_match('#/convites/([A-Za-z0-9]{64})#', $message, $match) !== 1) {
        throw new RuntimeException('Mensagem sintética sem convite.');
    }
    $tokens[] = ['email' => $recipient->email, 'token' => $match[1]];
}
file_put_contents('/tmp/e09-accept-samples.json', json_encode($tokens, JSON_THROW_ON_ERROR));
$times = array_column($samples, 'ms');
sort($times);
echo json_encode([
    'boundary' => 'CreateAccountInvitationAction, CLI, PostgreSQL, transporte em memória',
    'samples' => count($samples),
    'median_ms' => $times[4],
    'p95_ms' => $times[9],
    'queries' => array_values(array_unique(array_column($samples, 'queries'))),
    'peak_delta_bytes_max' => max(array_column($samples, 'peak_delta_bytes')),
]), PHP_EOL;
