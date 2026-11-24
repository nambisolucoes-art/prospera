<?php

use App\Domains\Acessos\Application\Actions\DefaultRolesAction;
use App\Domains\Acessos\Application\Actions\RevokeAccountInvitationAction;
use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Acessos\Domain\Models\AccountInvitation;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (getenv('DB_DATABASE') !== 'e09') {
    throw new RuntimeException('Somente laboratório.');
}
$account = Client::where('code', 'laboratorio-conta-3')->sole();
if ($argv[1] === 'setup') {
    [$role] = app(DefaultRolesAction::class)->execute($account->id);
    $invite = AccountInvitation::issue(
        $account->id, User::where('email', 'recipient@e09.test')->sole(), $role, AccessNode::tenant($account->id),
        null, null, str_repeat('r', 64), now()->addDay(),
    );
    $invite->saveOrFail();
    $data = json_decode(file_get_contents('/tmp/e09-fixtures.json'), true, flags: JSON_THROW_ON_ERROR);
    $data['invitations']['valid'] = str_repeat('r', 64);
    file_put_contents('/tmp/e09-fixtures.json', json_encode($data, JSON_THROW_ON_ERROR));
    echo json_encode(['result' => 'prepared']), PHP_EOL;
} else {
    $operator = User::where('email', 'emitter@e09.test')->sole();
    auth()->login($operator);
    app(AccountContext::class)->enter($operator, $account->code);
    $invite = AccountInvitation::where('account_id', $account->id)
        ->where('token_hash', AccountInvitation::hashToken(str_repeat('r', 64)))
        ->sole();
    AuditLog::transaction(function () use ($account, $invite): void {
        Client::whereKey($account->id)->lockForUpdate()->firstOrFail();
        file_put_contents('/tmp/e09-revoke-ready', 'locked');
        usleep(1500000);
        app(RevokeAccountInvitationAction::class)->execute($account->id, $invite->id);
    });
    echo json_encode(['result' => 'revoked']), PHP_EOL;
}
