<?php

// Dados exclusivamente sintéticos da cópia isolada. Executado depois de e04-fixtures.php.
use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Acessos\Domain\Models\RoleAssignment;
use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Organizacao\Application\Actions\Companies\CreateCompanyAction;
use App\Domains\Organizacao\Application\Actions\Establishments\CreateEstablishmentAction;
use App\Domains\Organizacao\Application\Data\Companies\CreateCompanyData;
use App\Domains\Organizacao\Application\Data\Establishments\CreateEstablishmentData;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function e06Cnpj(int $root, int $order): string
{
    $base = str_pad((string) $root, 8, '0', STR_PAD_LEFT).str_pad((string) $order, 4, '0', STR_PAD_LEFT);

    foreach ([[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]] as $weights) {
        $sum = 0;

        foreach ($weights as $index => $weight) {
            $sum += (ord($base[$index]) - 48) * $weight;
        }

        $base .= (string) ($sum % 11 < 2 ? 0 : 11 - $sum % 11);
    }

    return $base;
}

function e06Role(Client $account, string $name, array $slugs): Role
{
    $role = Role::create(['account_id' => $account->id, 'name' => $name]);
    $role->permissions()->attach(Permission::whereIn('slug', $slugs)->pluck('id'));

    return $role;
}

/** @param list<array{0: Role, 1: AccessNode}> $assignments */
function e06Member(Client $account, string $email, string $name, array $assignments): User
{
    $user = User::factory()->forAccount($account->id)->create(['email' => $email, 'name' => $name]);
    $membership = AccountMembership::where('user_id', $user->id)->sole();
    $membership->assignments()->delete();

    foreach ($assignments as [$role, $node]) {
        RoleAssignment::grant($membership, $role, $node, null)->saveOrFail();
    }

    return $user;
}

function e06Node(Establishment $unit): AccessNode
{
    return AccessNode::establishment($unit->account_id, $unit->company_id, $unit->id);
}

$manager = [
    'account.establishments.list',
    'account.establishments.view',
    'account.establishments.update',
    'account.companies.list',
    'account.companies.view',
    'self.help.list',
    'self.help.view',
    'self.profile.view',
];
$operator = ['account.establishments.list', 'account.establishments.view', 'self.help.list', 'self.help.view'];

// Cenários de medição na conta de 5 empresas e 50 unidades da E04.
$account = Client::where('name', 'Cenário 50')->sole();
$units = Establishment::where('account_id', $account->id)->orderBy('company_id')->orderBy('id')->get();
$companies = Company::where('account_id', $account->id)->orderBy('id')->get();
$managerRole = e06Role($account, 'E06 Gerente', $manager);
$operatorRole = e06Role($account, 'E06 Operador', $operator);
$users = [];

$users['unidade'] = e06Member($account, 'unidade@e06.test', 'Uma unidade', [[$operatorRole, e06Node($units[0])]]);
$users['unidades'] = e06Member($account, 'unidades@e06.test', 'Várias unidades', array_map(
    fn (Establishment $unit) => [$operatorRole, e06Node($unit)],
    [$units[0], $units[1], $units[2], $units[10], $units[11]],
));
$users['empresa'] = e06Member($account, 'empresa@e06.test', 'Empresa inteira', [
    [$managerRole, AccessNode::company($account->id, $companies[0]->id)],
]);
$users['cargos'] = e06Member($account, 'cargos@e06.test', 'Vários cargos', [
    [$managerRole, AccessNode::company($account->id, $companies[1]->id)],
    [$operatorRole, e06Node($units[0])],
    [$operatorRole, e06Node($units[30])],
    [$managerRole, e06Node($units[40])],
]);

$scenarios = [];

foreach (['1x1', '1x3', '3xN', '50'] as $key) {
    $owner = User::where('email', 'owner-'.strtolower($key).'@e04.test')->sole();
    $first = Establishment::where('account_id', $owner->account_id)->orderBy('company_id')->orderBy('id')->value('code');
    $scenarios['integral '.$key] = ['user' => $owner->id, 'code' => $first, 'units' => Establishment::where('account_id', $owner->account_id)->count()];
}

foreach ($users as $key => $user) {
    $assignments = AccountMembership::where('user_id', $user->id)->sole()->assignments()->get();
    $covered = $units->filter(fn (Establishment $unit) => $assignments->contains(
        fn (RoleAssignment $assignment) => $assignment->establishment_id === $unit->id
            || ($assignment->scope === 'company' && $assignment->company_id === $unit->company_id),
    ));
    $scenarios[$key] = ['user' => $user->id, 'code' => $covered->first()->code, 'units' => $covered->count()];
}

// Conta do exemplo da E06, usada na validação visual e na verificação de isolamento.
$gartan = Client::create(['name' => 'Grupo Gartan', 'active' => true]);
$owner = User::factory()->asAccountAdministrator($gartan->id)->create(['name' => 'Dono Gartan', 'email' => 'dono@gartan.test']);
$structure = [
    ['Gartan Roupas', 31222333, 'gartan-roupas', ['Guanambi', 'Janaúba']],
    ['Gartan Express', 32888777, 'gartan-express', ['Guanambi', 'Janaúba', 'Jequié']],
    ['Gartan 3', 33666777, 'gartan-3', ['Unidade principal']],
];
$gartanUnits = [];

foreach ($structure as [$name, $root, $code, $cities]) {
    $company = app(CreateCompanyAction::class)->execute(CreateCompanyData::fromInput([
        'person_type' => 'company',
        'name' => $name.' Ltda',
        'trade_name' => $name,
        'document' => e06Cnpj($root, 1),
        'code' => $code,
        'primary' => [
            'classification' => 'headquarters',
            'address' => ['city' => 'Guanambi', 'state' => 'BA'],
        ],
    ], $gartan->id), $gartan->id);

    $primary = $company->primaryEstablishment()->sole();
    $primary->forceFill(['name' => $cities[0]])->saveOrFail();
    $gartanUnits[$code][] = $primary;

    foreach (array_slice($cities, 1) as $order => $city) {
        $data = CreateEstablishmentData::fromInput([
            'name' => $city,
            'classification' => 'branch',
            'tax_document' => e06Cnpj($root, $order + 2),
            'address' => ['city' => $city, 'state' => $city === 'Janaúba' ? 'MG' : 'BA'],
        ], $company);

        $gartanUnits[$code][] = app(CreateEstablishmentAction::class)->execute($data, $company);
    }
}

$gartanManager = e06Role($gartan, 'Gerente', $manager);
$gartanOperator = e06Role($gartan, 'Operador', $operator);
$roupas = Company::where('account_id', $gartan->id)->where('code', 'gartan-roupas')->sole();
$ana = e06Member($gartan, 'ana@gartan.test', 'Ana Souza', [
    [$gartanManager, AccessNode::company($gartan->id, $roupas->id)],
    [$gartanOperator, e06Node($gartanUnits['gartan-express'][1])],
]);
$local = e06Member($gartan, 'local@gartan.test', 'Operador de Jequié', [
    [$gartanOperator, e06Node($gartanUnits['gartan-express'][2])],
]);

$single = Client::where('name', 'Cenário 1x1')->sole();
$platform = User::factory()->create(['is_saas_admin' => true, 'email' => 'saas@e06.test', 'name' => 'Administrador SaaS']);

file_put_contents('/tmp/prospera-e06-scenarios.json', json_encode([
    'scenarios' => $scenarios,
    'gartan' => [
        'account' => $gartan->id,
        'owner' => $owner->id,
        'ana' => $ana->id,
        'local' => $local->id,
        'platform' => $platform->id,
        'ana_units' => array_map(fn (Establishment $unit) => $unit->code, [
            ...$gartanUnits['gartan-roupas'],
            $gartanUnits['gartan-express'][1],
        ]),
        'express_janauba' => $gartanUnits['gartan-express'][1]->id,
        'hidden' => $gartanUnits['gartan-3'][0]->code,
        'single_owner' => User::where('email', 'owner-1x1@e04.test')->value('id'),
        'single_account' => $single->id,
    ],
], JSON_THROW_ON_ERROR));

DB::statement('ANALYZE');
echo "Cenários da E06 carregados.\n";
