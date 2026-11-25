<?php

use App\Domains\Acessos\Application\Actions\AcceptAccountInvitationAction;
use App\Domains\Acessos\Application\Actions\DefaultRolesAction;
use App\Domains\Acessos\Domain\Models\AccountInvitation;
use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (getenv('DB_DATABASE') !== 'e09') {
    throw new RuntimeException('Somente laboratório.');
}
$account = Client::where('code', 'laboratorio-conta-1')->sole();
[$role] = app(DefaultRolesAction::class)->execute($account->id);
$original = User::factory()->create(['email' => 'reassigned@e09.test']);
$invite = AccountInvitation::issue(
    $account->id, $original, $role, AccessNode::tenant($account->id), null, null,
    str_repeat('j', 64), now()->addDay(),
);
$invite->saveOrFail();
$original->forceFill(['email' => 'changed-recipient@e09.test', 'email_verified_at' => now()])->saveOrFail();
$other = User::factory()->create(['email' => 'reassigned@e09.test']);
auth()->login($other);
try {
    app(AcceptAccountInvitationAction::class)->execute($other, str_repeat('j', 64));
    throw new RuntimeException('A mudança de e-mail transferiu o convite.');
} catch (HttpExceptionInterface $exception) {
    if ($exception->getStatusCode() !== 410 || AccountMembership::where('user_id', $other->id)->exists()) {
        throw new RuntimeException('A outra identidade não pode receber vínculo.');
    }
    echo json_encode([
        'same_email_other_identity' => 'denied',
        'status' => 410,
        'original_recipient_preserved' => $invite->fresh()->recipient_id === $original->id,
        'membership_created' => false,
    ]), PHP_EOL;
}
