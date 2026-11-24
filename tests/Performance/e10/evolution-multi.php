<?php

// Código atual: dá a uma identidade um segundo vínculo, situação em que a contração deixa de ser reversível.
use App\Domains\Acessos\Application\Actions\DefaultRolesAction;
use App\Domains\Acessos\Application\Actions\RegisterAccountMembershipAction;
use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (getenv('DB_DATABASE') !== 'e10evo') {
    throw new RuntimeException('Somente o laboratório de evolução da E10.');
}

$user = User::where('email', 'ana.ribeiro@horizonte.test')->sole();
$account = DB::table('clients')->where('name', 'like', 'Árvore%')->orderBy('id')->value('id');
[, $member] = app(DefaultRolesAction::class)->execute($account);
app(RegisterAccountMembershipAction::class)->execute($user, $member, AccessNode::tenant($account));
echo 'vinculos da identidade: ', DB::table('account_memberships')->where('user_id', $user->id)->count(), PHP_EOL;
