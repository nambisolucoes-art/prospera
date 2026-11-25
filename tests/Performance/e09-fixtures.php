<?php

// Executar exclusivamente no PostgreSQL descartável do laboratório E09.
use App\Domains\Acessos\Application\Actions\DefaultRolesAction;
use App\Domains\Acessos\Application\Actions\RegisterAccountMembershipAction;
use App\Domains\Acessos\Domain\Models\AccountInvitation;
use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Organizacao\Application\Actions\Companies\CreateCompanyAction;
use App\Domains\Organizacao\Application\Actions\Establishments\CreateEstablishmentAction;
use App\Domains\Organizacao\Application\Data\Companies\CreateCompanyData;
use App\Domains\Organizacao\Application\Data\Establishments\CreateEstablishmentData;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (getenv('DB_DATABASE') !== 'e09') {
    throw new RuntimeException('Somente o laboratório E09.');
}

$accounts = [];
foreach (range(1, 5) as $number) {
    $account = Client::firstOrCreate(['name' => 'Laboratório Conta '.$number], ['active' => true]);
    $company = app(CreateCompanyAction::class)->execute(CreateCompanyData::fromInput([
        'name' => 'Empresa '.$number,
        'person_type' => 'company',
        'document' => str_pad((string) (11000000 + $number), 8, '0', STR_PAD_LEFT),
        'active' => true,
        'primary' => ['classification' => 'unregistered'],
    ], $account->id), $account->id);
    if ($number === 1) {
        app(CreateEstablishmentAction::class)->execute(CreateEstablishmentData::fromInput([
            'name' => 'Unidade adicional',
            'classification' => 'unregistered',
        ], $company), $company);
    }
    $accounts[] = $account;
}
$user = User::factory()->asAccountAdministrator($accounts[0]->id)->create([
    'name' => 'Identidade Duas Contas', 'email' => 'multi@e09.test',
]);
$limited = Role::create(['account_id' => $accounts[1]->id, 'name' => 'Operador limitado']);
$limited->permissions()->sync(Permission::whereIn('slug', [
    'account.tags.list', 'account.establishments.list', 'account.establishments.view',
    'account.companies.list', 'account.companies.view', 'self.help.list', 'self.help.view',
])->pluck('id'));
app(RegisterAccountMembershipAction::class)->execute($user, $limited, AccessNode::tenant($accounts[1]->id));
$single = User::factory()->asAccountAdministrator($accounts[0]->id)->create(['email' => 'single@e09.test']);
$five = User::factory()->asAccountAdministrator($accounts[0]->id)->create(['email' => 'five@e09.test']);
foreach (array_slice($accounts, 1) as $account) {
    [$role] = app(DefaultRolesAction::class)->execute($account->id);
    app(RegisterAccountMembershipAction::class)->execute(
        $five,
        $role,
        AccessNode::tenant($account->id),
    );
}
$none = User::factory()->create(['email' => 'none@e09.test']);
$suspended = User::factory()->forAccount($accounts[0]->id)->create(['email' => 'suspended@e09.test']);
$suspended->memberships()->sole()->forceFill(['status' => 'suspended'])->saveOrFail();
$platform = User::factory()->create(['email' => 'platform@e09.test', 'is_saas_admin' => true]);
$recipient = User::factory()->create(['email' => 'recipient@e09.test']);
$expiredRecipient = User::factory()->create(['email' => 'expired@e09.test']);
[$role] = app(DefaultRolesAction::class)->execute($accounts[0]->id);
$invites = [];
foreach (['valid', 'expired', 'revoked'] as $index => $state) {
    $token = str_repeat((string) ($index + 1), 64);
    $invite = AccountInvitation::issue(
        $accounts[0]->id,
        $state === 'expired' ? $expiredRecipient : $recipient,
        $role,
        AccessNode::tenant($accounts[0]->id),
        null, $single->id, $token, $state === 'expired' ? now()->subDay() : now()->addDay(),
    );
    if ($state === 'revoked') {
        $invite->markRevoked();
    }
    $invite->saveOrFail();
    $invites[$state] = $token;
}
$data = [
    'accounts' => array_map(fn ($a) => ['id' => $a->id, 'code' => $a->code], $accounts),
    'users' => compact('user', 'single', 'five', 'none', 'suspended', 'platform', 'recipient'),
    'invitations' => $invites,
];
$data['users'] = array_map(fn ($u) => $u->id, $data['users']);
file_put_contents('/tmp/e09-fixtures.json', json_encode($data, JSON_THROW_ON_ERROR));
echo json_encode($data, JSON_THROW_ON_ERROR), PHP_EOL;
