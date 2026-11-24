<?php

use App\Domains\Acessos\Application\Actions\SaveRoleAction;
use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Application\Data\RoleAvailabilityData;
use App\Domains\Acessos\Application\Data\SaveRoleData;
use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Domains\Usuarios\Application\Actions\CreateUserAction;
use App\Domains\Usuarios\Application\Data\CreateUserData;
use App\Domains\Usuarios\Domain\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (getenv('DB_DATABASE') !== 'e09') {
    throw new RuntimeException('Somente laboratório.');
}
$account = Client::where('code', 'laboratorio-conta-1')->sole();
if ($argv[1] === 'setup') {
    $role = Role::create(['account_id' => $account->id, 'name' => 'Concessão concorrente']);
    $role->permissions()->sync(Permission::whereIn('slug', [
        'account.users.create', 'account.users.assign-role', 'self.help.list',
    ])->pluck('id'));
    $target = Role::create(['account_id' => $account->id, 'name' => 'Alvo concorrente']);
    $target->permissions()->sync(Permission::where('slug', 'self.help.list')->pluck('id'));
    $operator = User::factory()->forAccount($account->id, $role)->create(['email' => 'grant-operator@e09.test']);
    file_put_contents('/tmp/e09-grant.json', json_encode([
        'operator' => $operator->id, 'role' => $role->id, 'target' => $target->id,
    ], JSON_THROW_ON_ERROR));
    echo json_encode(['result' => 'prepared']), PHP_EOL;
    exit;
}
$data = json_decode(file_get_contents('/tmp/e09-grant.json'), true, flags: JSON_THROW_ON_ERROR);
$user = $argv[1] === 'revoke'
    ? User::where('email', 'five@e09.test')->sole()
    : User::whereKey($data['operator'])->firstOrFail();
auth()->login($user);
app(AccountContext::class)->enter($user, $account->code);
if ($argv[1] === 'revoke') {
    $role = Role::where('account_id', $account->id)->findOrFail($data['role']);
    app(SaveRoleAction::class)->execute(
        $account->id, $role,
        SaveRoleData::fromInput([
            'name' => $role->name,
            'permission_ids' => Permission::whereIn('slug', ['account.users.assign-role', 'self.help.list'])
                ->pluck('id')
                ->all(),
        ], $account->id, $role->id),
        RoleAvailabilityData::fromInput([], $account->id),
    );
    file_put_contents('/tmp/e09-grant-release', 'committed');
    echo json_encode(['result' => 'revoked']), PHP_EOL;
    exit;
}
$permissions = app(EffectivePermissions::class);
if (! $permissions->allows($user, 'account.users.create', AccessNode::tenant($account->id))) {
    throw new RuntimeException('A permissão inicial deveria existir.');
}
file_put_contents('/tmp/e09-grant-ready', 'cached');
for ($attempt = 0; $attempt < 100 && ! is_file('/tmp/e09-grant-release'); $attempt++) {
    usleep(100000);
}
if (! is_file('/tmp/e09-grant-release')) {
    throw new RuntimeException('O processo revogador não confirmou a transação.');
}
if (! $permissions->allows($user, 'account.users.create', AccessNode::tenant($account->id))) {
    throw new RuntimeException('A prova requer o snapshot inicial ainda memorizado.');
}
try {
    app(CreateUserAction::class)->execute(
        CreateUserData::fromInput([
            'name' => 'Concessão negada',
            'email' => 'grant-denied@e09.test',
            'mode' => 'invite',
        ]),
        UserRole::AccountMember,
        $account,
        roleId: $data['target'],
        node: AccessNode::tenant($account->id),
    );
    throw new RuntimeException('A revogação não foi observada.');
} catch (AuthorizationException) {
    if (User::where('email', 'grant-denied@e09.test')->exists()) {
        throw new RuntimeException('Identidade indevidamente persistida.');
    }
    echo json_encode(['result' => 'denied-after-concurrent-revocation', 'identity_created' => false]), PHP_EOL;
}
