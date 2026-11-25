<?php

// Código pré-E09: completa a massa do seeder com preferências da Área de Trabalho e uma segunda conta.
use App\Domains\Plataforma\Application\Actions\Accounts\CreateAccountAction;
use App\Domains\Plataforma\Application\Data\Accounts\CreateAccountData;
use App\Models\User;
use Database\Seeders\Support\FiscalDocuments;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (getenv('DB_DATABASE') !== 'e10evo') {
    throw new RuntimeException('Somente o laboratório de evolução da E10.');
}

// Preferências pessoais reais: códigos de duas unidades navegáveis da administradora.
$codes = DB::table('establishments')->orderBy('id')->limit(2)->pluck('code')->all();
DB::table('account_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')
    ->where('u.email', 'ana.ribeiro@horizonte.test')
    ->update(['workspace_establishments' => json_encode($codes)]);

// Segunda conta pela Action da plataforma, com nome acentuado e repetido (códigos distintos na evolução).
Auth::guard('web')->setUser(User::where('email', 'admin@prospera.test')->sole());
foreach (['Árvore & Ação Comércio', 'Árvore & Ação Comércio'] as $index => $name) {
    app()->forgetScopedInstances();
    $document = FiscalDocuments::cnpj('7123456'.$index, '0001');
    app(CreateAccountAction::class)->execute(CreateAccountData::fromInput([
        'name' => $name, 'person_type' => 'company', 'document' => $document, 'details' => ['phone' => '7134567890'],
        'organization_setup' => 'automatic', 'contractor_classification' => 'headquarters',
        'administrator' => ['name' => 'Admin '.$index, 'email' => 'evolucao'.$index.'@e10.test', 'mode' => 'manual',
            'password' => 'Sintetica-E10-senha', 'password_confirmation' => 'Sintetica-E10-senha'],
    ]));
}

echo json_encode([
    'clients' => DB::table('clients')->count(),
    'users_com_conta' => DB::table('users')->whereNotNull('account_id')->count(),
    'memberships' => DB::table('account_memberships')->count(),
    'assignments' => DB::table('role_assignments')->count(),
    'customers' => DB::table('customers')->count(),
    'suppliers' => DB::table('suppliers')->count(),
    'audit' => DB::table('audit_records')->count(),
    'suspensos' => DB::table('account_memberships')->where('status', 'suspended')->count(),
    'preferencias' => DB::table('account_memberships')->whereNotNull('workspace_establishments')->count(),
]), PHP_EOL;
