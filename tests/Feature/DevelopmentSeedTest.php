<?php

namespace Tests\Feature;

use App\Domains\Clientes\Domain\Models\Customer;
use App\Domains\Fornecedores\Domain\Models\Supplier;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Support\FiscalDocuments;
use Database\Seeders\Support\SeedCatalog;
use Database\Seeders\Support\SeedSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** O cenário de make seed precisa continuar válido conforme as regras da aplicação evoluem. */
final class DevelopmentSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_documents_have_valid_check_digits(): void
    {
        self::assertSame('52998224725', FiscalDocuments::cpf('529982247'));
        self::assertSame('11222333000181', FiscalDocuments::cnpj('11222333', '0001'));
    }

    public function test_the_scenario_is_created_through_the_application_rules(): void
    {
        $this->seed(DatabaseSeeder::class);

        $account = Client::sole();
        self::assertSame(4, DB::table('companies')->count());
        self::assertSame(10, DB::table('establishments')->count());
        self::assertSame(11, User::count());
        self::assertSame(13, Customer::count());
        self::assertSame(11, Supplier::count());

        $duplicates = DB::table('people')
            ->whereNotNull('document')
            ->select('document')
            ->groupBy('document')
            ->havingRaw('count(*) > 1')
            ->count();
        self::assertSame(0, $duplicates);

        $shared = DB::table('people')
            ->where('document', FiscalDocuments::cnpj('50417293', '0001'))
            ->value('id');
        self::assertTrue(Customer::where('person_id', $shared)->exists());
        self::assertTrue(Supplier::where('person_id', $shared)->exists());

        $this->assertDatabaseHas('users', ['email' => SeedCatalog::PLATFORM_ADMINISTRATOR, 'is_saas_admin' => true]);
        $this->assertDatabaseHas('audit_records', ['account_id' => $account->id, 'entity_type' => 'tag']);

        $summary = array_column(SeedSummary::users(), 1, 0);
        self::assertCount(11, $summary);
        self::assertSame('Administrador da plataforma SaaS', $summary[SeedCatalog::PLATFORM_ADMINISTRATOR]);
        self::assertSame(
            'Gerente de loja em Loja Brumado; Gerente de loja em Loja Guanambi; Gerente de loja em Centro de Distribuição',
            $summary['carla.souza@horizonte.test'],
        );
        self::assertStringEndsWith('(vínculo suspenso)', $summary['igor.pereira@horizonte.test']);
    }

    public function test_seeded_profiles_respect_their_places_and_status(): void
    {
        $this->seed(DatabaseSeeder::class);
        $base = '/contas/'.Client::sole()->code;

        $this->post('/login', ['email' => SeedCatalog::ACCOUNT_ADMINISTRATOR, 'password' => SeedCatalog::PASSWORD]);
        $this->assertAuthenticatedAs(User::where('email', SeedCatalog::ACCOUNT_ADMINISTRATOR)->sole());

        $cashier = User::where('email', 'diego.lima@horizonte.test')->sole();
        $this->actingAs($cashier);
        $this->getJson($base.'/estabelecimento/'.SeedCatalog::BRUMADO_STORE)->assertOk();
        $this->getJson($base.'/estabelecimento/'.SeedCatalog::SHOPPING_PHARMACY)->assertOk();
        $this->getJson($base.'/estabelecimento/'.SeedCatalog::GUANAMBI_STORE)->assertNotFound();
        $this->getJson($base.'/auditoria')->assertForbidden();

        $manager = User::where('email', 'carla.souza@horizonte.test')->sole();
        $this->actingAs($manager);

        foreach ([SeedCatalog::BRUMADO_STORE, SeedCatalog::GUANAMBI_STORE, SeedCatalog::DISTRIBUTION_CENTER] as $unit) {
            $this->getJson($base.'/estabelecimento/'.$unit)->assertOk();
        }

        $this->getJson($base.'/estabelecimento/'.SeedCatalog::CANDEIAS_PHARMACY)->assertNotFound();

        $this->actingAs(User::where('email', 'felipe.rocha@horizonte.test')->sole());
        $this->getJson($base.'/auditoria')->assertOk();

        $this->actingAs(User::where('email', 'igor.pereira@horizonte.test')->sole());
        $this->getJson($base)->assertForbidden();
    }
}
