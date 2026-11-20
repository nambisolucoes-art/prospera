<?php

use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Auditoria\Domain\Models\AuditRecord;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (($argv[1] ?? '') === 'revoke') {
    $role = Role::where('name', 'Perfil 10')->sole();
    $role->permissions()->detach(Permission::where('slug', 'self.help.list')->value('id'));

    exit;
}

if (($argv[1] ?? '') === 'check-audit') {
    $records = AuditRecord::where('entity_type', 'point')->where('event', 'created')->orderBy('id')->get();
    if (
        $records->count() !== 2
        || $records[0]->actor_id !== 43
        || $records[0]->represented_user_id !== 1
        || $records[0]->account_id !== 1
        || $records[1]->actor_id !== 1
        || $records[1]->represented_user_id !== null
        || $records[1]->account_id !== 1
    ) {
        throw new RuntimeException('Autoria de inspeção incorreta.');
    }

    exit;
}

$first = Client::create(['name' => 'Conta A', 'active' => true]);
$second = Client::create(['name' => 'Conta B', 'active' => true]);
$owner = User::factory()->create(['account_id' => $first->id, 'is_account_admin' => true]);
$member = Role::where('account_id', $first->id)->where('system_key', 'member')->sole();
User::factory()->count(39)->create(['account_id' => $first->id, 'role_id' => $member->id]);
foreach ([10, 40] as $size) {
    $role = Role::create(['account_id' => $second->id, 'name' => 'Perfil '.$size]);
    $ids = Permission::whereIn('scope', ['self', 'account'])
        ->orderByRaw("case when slug in ('self.help.list', 'account.dashboard.view') then 0 else 1 end")
        ->orderBy('id')
        ->limit($size)
        ->pluck('id');
    $role->permissions()->sync($ids);
    User::factory()->create(['account_id' => $second->id, 'role_id' => $role->id]);
}
User::factory()->create(['is_saas_admin' => true]);
DB::statement('ANALYZE');

if ($owner->id !== 1) {
    throw new RuntimeException('A medição exige um banco sintético novo.');
}
