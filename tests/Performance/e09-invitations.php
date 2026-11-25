<?php

use App\Domains\Acessos\Application\Actions\CreateAccountInvitationAction;
use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Mail;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (getenv('DB_DATABASE') !== 'e09' || config('mail.default') !== 'array') {
    throw new RuntimeException('Somente laboratório com transporte de e-mail em memória.');
}
$user = User::where('email', 'five@e09.test')->sole();
$account = Client::where('code', $argv[1] ?? 'laboratorio-conta-2')->sole();
auth()->login($user);
app(AccountContext::class)->enter($user, $account->code);
$role = Role::where('account_id', $account->id)->where('system_key', 'administrator')->sole();
app(CreateAccountInvitationAction::class)->execute(
    $account, 'recipient@e09.test', $role->id, AccessNode::tenant($account->id), null,
);
$messages = Mail::getSymfonyTransport()->messages();
$message = $messages->last()->getOriginalMessage()->getTextBody();
if (preg_match('#/convites/([A-Za-z0-9]{64})#', $message, $match) !== 1) {
    throw new RuntimeException('Nenhum convite entregue ao transporte sintético.');
}
file_put_contents('/tmp/e09-browser-invite.txt', $match[1]);
echo json_encode(['delivered_to_memory' => $messages->count(), 'recipient' => 'recipient@e09.test']), PHP_EOL;
