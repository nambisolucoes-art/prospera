<?php

// S07: massa sintética do laboratório descartável (contas A e B, usuários com permissões distintas e uma rede de 40 unidades).
// A senha dos usuários vem de LAB_PASSWORD, nunca versionada. Só roda no banco `s07`.
use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Acessos\Domain\Models\RoleAssignment;
use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Organizacao\Application\Actions\Companies\CreateCompanyAction;
use App\Domains\Organizacao\Application\Actions\Establishments\CreateEstablishmentAction;
use App\Domains\Organizacao\Application\Data\Companies\CreateCompanyData;
use App\Domains\Organizacao\Application\Data\Establishments\CreateEstablishmentData;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (getenv('DB_DATABASE') !== 's07') {
    throw new RuntimeException('Somente o laboratório S07.');
}

$password = getenv('LAB_PASSWORD') ?: throw new RuntimeException('Defina LAB_PASSWORD.');
$hash = Hash::make($password);

$setup = function (string $accountName, string $adminEmail, callable $build) use ($hash, $app): void {
    $account = Client::create(['name' => $accountName, 'active' => true]);
    $admin = User::factory()->asAccountAdministrator($account->id)->create(['email' => $adminEmail, 'password' => $hash]);
    Auth::login($admin);
    $app->make(AccountContext::class)->enter($admin, $account->code);
    $build($account);
};

$company = fn (Client $account, string $name, string $root) => app(CreateCompanyAction::class)
    ->execute(CreateCompanyData::fromInput(['name' => $name, 'person_type' => 'company', 'document' => $root], $account->id), $account->id);
$unit = fn ($company, string $name) => app(CreateEstablishmentAction::class)
    ->execute(CreateEstablishmentData::fromInput(['name' => $name, 'classification' => 'unregistered'], $company), $company);
$operator = function (Client $account, string $email, array $slugs, AccessNode $node) use ($hash): void {
    $role = Role::create(['name' => 'Papel '.$email, 'account_id' => $account->id]);
    $role->permissions()->attach(Permission::whereIn('slug', $slugs)->pluck('id'));
    $user = User::factory()->forAccount($account->id)->create(['email' => $email, 'password' => $hash]);
    $membership = DB::table('account_memberships')->where('user_id', $user->id)->where('account_id', $account->id)->first();
    $model = AccountMembership::find($membership->id);
    $model->assignments()->delete();
    RoleAssignment::grant($model, $role, $node, null)->saveOrFail();
};

$setup('Grupo Gartan', 'admin@s07.test', function (Client $account) use ($company, $unit, $operator): void {
    $roupas = $company($account, 'Gartan Roupas', '11222333');
    $express = $company($account, 'Gartan Express', '44555666');
    $unit($roupas, 'Guanambi');
    $unit($roupas, 'Caetité');
    $manaus = $unit($roupas, 'Manaus');
    $newYork = $unit($roupas, 'Nova York');
    DB::table('establishments')->where('id', $manaus->id)->update(['timezone' => 'America/Manaus']);
    DB::table('establishments')->where('id', $newYork->id)->update(['timezone' => 'America/New_York']);

    $view = ['account.institutional.view'];
    $edit = ['account.institutional.view', 'account.institutional.update'];
    $operator($account, 'viewer@s07.test', $view, AccessNode::company($account->id, $roupas->id));
    $operator($account, 'ed@s07.test', $edit, AccessNode::company($account->id, $roupas->id));
    $operator($account, 'gui@s07.test', $edit, AccessNode::establishment($account->id, $roupas->id, DB::table('establishments')->where('code', 'like', '%guanambi')->value('id')));

    $rede = $company($account, 'Rede Quarenta', '55666777');
    foreach (range(1, 39) as $number) {
        $unit($rede, 'Loja '.$number);
    }
});

$setup('Grupo B', 'b@s07.test', function (Client $account) use ($company): void {
    $company($account, 'Empresa B', '99888777');
});

User::factory()->create(['name' => 'Plataforma S07', 'email' => 'platform@s07.test', 'is_saas_admin' => true, 'password' => $hash]);

echo 'fixtures-s07=ok', PHP_EOL;
