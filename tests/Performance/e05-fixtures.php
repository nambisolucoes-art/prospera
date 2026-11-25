<?php

// Dados exclusivamente sintéticos da cópia isolada.
use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Acessos\Domain\Models\RoleAssignment;
use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$account = Client::where('name', 'Cenário 50')->sole();
$units = Establishment::where('account_id', $account->id)->orderBy('id')->get();
$permissions = Permission::whereIn('slug', ['account.establishments.list', 'account.audit.list'])->pluck('id');
$roles = [];

foreach (['Leitura de unidades', 'Histórico local'] as $name) {
    $role = Role::create(['account_id' => $account->id, 'name' => $name]);
    $role->permissions()->attach($permissions);
    $roles[] = $role;
}

$scenarios = [];
foreach ([1, 5, 30] as $count) {
    $user = User::factory()->forAccount($account->id)->create();
    $membership = AccountMembership::where('user_id', $user->id)->sole();
    $membership->assignments()->delete();

    foreach ($units->take($count) as $index => $unit) {
        $node = AccessNode::establishment($account->id, $unit->company_id, $unit->id);
        RoleAssignment::grant($membership, $roles[$index % count($roles)], $node, null)->saveOrFail();
    }

    $scenarios[$count] = [
        'user' => $user->id,
        'rows' => $count,
        'roles' => min($count, 2),
        'assignment_rows' => $count,
        'permission_rows' => min($count, 2) * $permissions->count(),
    ];
}

$membership = AccountMembership::where('account_id', $account->id)
    ->whereHas('assignments', fn ($assignments) => $assignments->where('scope', 'tenant')
        ->whereHas('role', fn ($role) => $role->where('system_key', 'administrator')))
    ->sole();
$owner = $membership->identity()->sole();
$scenarios['integral'] = [
    'user' => $owner->id,
    'rows' => 50,
    'roles' => 1,
    'assignment_rows' => 1,
    'permission_rows' => DB::table('permission_role')->where('role_id', $membership->assignments()->sole()->role_id)->count(),
];

file_put_contents('/tmp/prospera-e05-scenarios.json', json_encode($scenarios, JSON_THROW_ON_ERROR));

$secondAdmin = User::factory()->asAccountAdministrator($account->id)->create();
$secondMembership = AccountMembership::where('user_id', $secondAdmin->id)->sole();
$target = AccountMembership::where('user_id', $scenarios[1]['user'])->sole();
$unit = $units->last();

file_put_contents('/tmp/prospera-e05-race.json', json_encode([
    'account' => $account->id,
    'grant' => [
        'actor' => $owner->id,
        'membership' => $target->id,
        'role' => $roles[0]->id,
        'company' => $unit->company_id,
        'unit' => $unit->id,
    ],
    'suspensions' => [
        ['actor' => $owner->id, 'membership' => $secondMembership->id],
        ['actor' => $secondAdmin->id, 'membership' => $membership->id],
    ],
], JSON_THROW_ON_ERROR));

$platform = User::factory()->create(['is_saas_admin' => true]);
file_put_contents('/tmp/prospera-e05-inspection.json', json_encode([
    'account' => $account->id,
    'platform' => $platform->id,
    'platform_email' => $platform->email,
    'owner' => $owner->id,
    'owner_email' => $owner->email,
    'operator' => $target->user_id,
    'membership' => $membership->id,
], JSON_THROW_ON_ERROR));
