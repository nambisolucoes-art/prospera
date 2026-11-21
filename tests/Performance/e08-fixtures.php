<?php

// Dados exclusivamente sintéticos da cópia isolada. Executado depois de e04-fixtures.php e e05-fixtures.php.
use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Acessos\Domain\Models\RoleAssignment;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$account = Client::where('name', 'Cenário 50')->sole();
$units = Establishment::where('account_id', $account->id)
    ->orderBy('company_id')
    ->orderBy('id')
    ->get();

$slugs = ['account.audit.list', 'account.audit.view', 'account.audit.technical'];
$role = Role::create(['account_id' => $account->id, 'name' => 'Auditoria local']);
$role->permissions()->attach(Permission::whereIn('slug', $slugs)->pluck('id'));

$grant = function (AccessNode $node) use ($account, $role): User {
    $user = User::factory()->forAccount($account->id)->create();
    $membership = AccountMembership::where('user_id', $user->id)->sole();
    $membership->assignments()->delete();
    RoleAssignment::grant($membership, $role, $node, null)->saveOrFail();

    return $user;
};

$firstUnit = $units->first();
$lastUnit = $units->last();
$companyAdmin = $grant(AccessNode::company($account->id, $firstUnit->company_id));
$unitAdmin = $grant(AccessNode::establishment($account->id, $lastUnit->company_id, $lastUnit->id));

$e05 = json_decode(file_get_contents('/tmp/prospera-e05-scenarios.json'), true, flags: JSON_THROW_ON_ERROR);
$others = Client::where('id', '!=', $account->id)
    ->orderBy('id')
    ->get();

$owners = [];

foreach ($others as $other) {
    $owners[] = AccountMembership::where('account_id', $other->id)
        ->orderBy('id')
        ->firstOrFail()
        ->user_id;
}

$platform = User::factory()->create(['is_saas_admin' => true, 'name' => 'Inspetora E08']);

file_put_contents('/tmp/prospera-e08-scenarios.json', json_encode([
    'account' => $account->id,
    'company' => $firstUnit->company_id,
    'establishment' => $lastUnit->id,
    'establishment_company' => $lastUnit->company_id,
    'platform' => $platform->id,
    'platform_email' => $platform->email,
    'other_owners' => $owners,
    'scenarios' => [
        'integral' => ['user' => $e05['integral']['user'], 'view' => true],
        'empresa' => ['user' => $companyAdmin->id, 'view' => true],
        'estabelecimento' => ['user' => $unitAdmin->id, 'view' => true],
        'atribuicoes-1' => ['user' => $e05[1]['user'], 'view' => false],
        'atribuicoes-5' => ['user' => $e05[5]['user'], 'view' => false],
        'atribuicoes-30' => ['user' => $e05[30]['user'], 'view' => false],
    ],
], JSON_THROW_ON_ERROR));

echo "Perfis da E08 carregados.\n";
