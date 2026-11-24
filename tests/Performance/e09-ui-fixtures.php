<?php

use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Organizacao\Application\Actions\CreateCompanyAction;
use App\Domains\Organizacao\Application\Data\SaveCompanyData;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (getenv('DB_DATABASE') !== 'e09') {
    throw new RuntimeException('Somente laboratório.');
}
$account = Client::create(['name' => 'Inativa E09', 'active' => true]);
app(CreateCompanyAction::class)->execute($account->id,
    SaveCompanyData::fromInput([
        'name' => 'Empresa inativa', 'person_type' => 'company', 'document' => '33000001',
        'primary' => ['classification' => 'unregistered'],
    ], $account->id, null),
);
User::factory()->asAccountAdministrator($account->id)->create(['email' => 'inactive@e09.test']);
$account->forceFill(['active' => false])->saveOrFail();
User::factory()->create(['email' => 'revoked@e09.test', 'access_enabled' => false]);
// Cargo só com a Central de Ajuda: a entrada na conta leva à Ajuda, sem tela de erro.
$helpOnly = Role::create(['account_id' => 1, 'name' => 'Somente ajuda E09']);
$helpOnly->permissions()->sync(Permission::whereIn('slug', ['self.help.list', 'self.help.view'])->pluck('id'));
User::factory()->forAccount(1, $helpOnly)->create(['email' => 'help-only@e09.test']);
echo json_encode(['states' => 'prepared']),PHP_EOL;

foreach (range(1, 30) as $n) {
    Tag::forceCreate([
        'account_id' => 1,
        'module' => 'customers',
        'name' => 'Paginação E09 '.str_pad((string) $n, 2, '0', STR_PAD_LEFT),
        'color' => '#123456',
        'active' => true,
    ]);
}
