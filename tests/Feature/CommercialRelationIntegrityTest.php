<?php

namespace Tests\Feature;

use App\Domains\Auditoria\Domain\Models\AuditRecord;
use App\Domains\Clientes\Domain\Models\Customer;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Fornecedores\Domain\Models\Supplier;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Feature\Concerns\BuildsCommercialScenario;
use Tests\TestCase;

/**
 * E07: Pessoa central da conta e relações comerciais independentes por empresa (itens 1 a 11 e migração).
 */
final class CommercialRelationIntegrityTest extends TestCase
{
    use BuildsCommercialScenario;
    use RefreshDatabase;

    private Client $gartan;

    private Company $roupas;

    private Company $express;

    private Company $gartan3;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gartan = $this->account();
        $this->roupas = $this->company($this->gartan, 'Gartan Roupas', '11222333');
        $this->express = $this->company($this->gartan, 'Gartan Express', '44555666');
        $this->gartan3 = $this->company($this->gartan, 'Gartan 3', '77888999');
        $this->owner = $this->administrator($this->gartan);
    }

    /** @param array<int, array<string,string>> $relations */
    private function saveJoao(array $relations, ?Person $person = null): Person
    {
        $payload = [
            'name' => 'João da Silva',
            'person_type' => 'individual',
            'document' => '52998224725',
            'relations' => $relations,
            // Preserva as etiquetas atuais: retirar Usuário suspenderia o acesso da pessoa.
            'tag_ids' => $person?->tags()->pluck('tags.id')->all() ?? [],
        ];

        if ($person === null) {
            $this->actingAs($this->owner)->postJson('/conta/pessoas', $payload)->assertOk();

            return Person::where('document', '52998224725')->sole();
        }

        $this->actingAs($this->owner)->patchJson('/conta/pessoas/'.$person->id, $payload)->assertOk();

        return $person;
    }

    public function test_one_central_person_holds_independent_relations_with_each_company(): void
    {
        // Itens 1 a 5: sem relação, cliente, fornecedor, ambos e várias empresas.
        $joao = $this->saveJoao([]);
        $this->assertSame(0, $joao->customers()->count() + $joao->suppliers()->count());

        $this->saveJoao([
            $this->roupas->id => ['customer' => '1', 'supplier' => '1'],
            $this->express->id => ['customer' => '1'],
        ], $joao);

        $this->assertDatabaseCount('people', 1 + Person::where('account_id', $this->gartan->id)
            ->where('id', '!=', $joao->id)->count());
        $this->assertSame(
            [$this->roupas->id, $this->express->id],
            $joao->customers()->orderBy('company_id')->pluck('company_id')->all(),
        );
        $this->assertSame([$this->roupas->id], $joao->suppliers()->pluck('company_id')->all());
        $this->assertDatabaseMissing('customers', ['company_id' => $this->gartan3->id]);
        $this->assertDatabaseMissing('suppliers', ['company_id' => $this->express->id]);

        $preview = $this->getJson('/conta/pessoas/'.$joao->id.'/visualizar')->assertOk()->json('relations');
        $this->assertEqualsCanonicalizing([
            [
                'company_id' => $this->express->id,
                'company_name' => 'Gartan Express',
                'company_active' => true,
                'customer' => 'active',
                'supplier' => 'none',
            ],
            [
                'company_id' => $this->roupas->id,
                'company_name' => 'Gartan Roupas',
                'company_active' => true,
                'customer' => 'active',
                'supplier' => 'active',
            ],
        ], $preview);
    }

    public function test_database_rejects_duplicate_and_cross_tenant_relations(): void
    {
        // Itens 6 e 7: o banco garante a unicidade e o mesmo tenant, independentemente da aplicação.
        $joao = $this->saveJoao([$this->roupas->id => ['customer' => '1']]);
        $customer = Customer::sole();

        try {
            DB::table('customers')->insert([
                'account_id' => $this->gartan->id,
                'company_id' => $this->roupas->id,
                'person_id' => $joao->id,
                'active' => true,
            ]);
            $this->fail('A relação repetida deveria ser recusada.');
        } catch (UniqueConstraintViolationException) {
            $this->assertDatabaseCount('customers', 1);
        }

        $other = $this->account('Outra conta');
        $foreign = $this->company($other, 'Empresa de fora', '12312312');

        foreach ([
            ['company_id' => $foreign->id, 'person_id' => $joao->id, 'account_id' => $this->gartan->id],
            ['company_id' => $foreign->id, 'person_id' => $joao->id, 'account_id' => $other->id],
            ['company_id' => $this->express->id, 'person_id' => $joao->id, 'account_id' => $other->id],
        ] as $row) {
            try {
                DB::table('suppliers')->insert([...$row, 'active' => true]);
                $this->fail('A relação entre tenants diferentes deveria ser recusada.');
            } catch (QueryException) {
                $this->assertDatabaseCount('suppliers', 0);
            }
        }

        // Pela aplicação, a mesma escolha repetida não cria nova linha nem novo evento.
        $count = AuditRecord::count();
        $this->saveJoao([$this->roupas->id => ['customer' => '1']], $joao);
        $this->assertSame($count, AuditRecord::count());
        $this->assertSame($customer->id, Customer::sole()->id);
    }

    public function test_inactivation_and_reactivation_are_independent_per_company_and_role(): void
    {
        // Itens 9 a 11: a relação muda sozinha; pessoa, outras relações, login e vínculo ficam intactos.
        $member = User::factory()->forAccount($this->gartan->id)->create();
        $person = $this->membership($member)->person;
        $identity = $member->only(['email', 'password', 'access_enabled']);
        $this->saveJoao([
            $this->roupas->id => ['customer' => '1', 'supplier' => '1'],
            $this->express->id => ['customer' => '1'],
        ], $person);

        $this->saveJoao([$this->roupas->id => ['supplier' => '0']], $person);
        $this->assertFalse(Supplier::where('company_id', $this->roupas->id)->sole()->active);
        $this->assertTrue(Customer::where('company_id', $this->roupas->id)->sole()->active);
        $this->assertTrue(Customer::where('company_id', $this->express->id)->sole()->active);

        $this->saveJoao([$this->roupas->id => ['customer' => '0']], $person);
        $this->assertFalse(Customer::where('company_id', $this->roupas->id)->sole()->active);
        $this->assertTrue(Customer::where('company_id', $this->express->id)->sole()->active);
        $this->assertSame('João da Silva', $person->fresh()->name);

        $this->saveJoao([$this->roupas->id => ['customer' => '1']], $person);
        $this->assertTrue(Customer::where('company_id', $this->roupas->id)->sole()->active);
        $this->assertFalse(Supplier::where('company_id', $this->roupas->id)->sole()->active);
        $this->assertDatabaseCount('customers', 2);

        $this->assertSame($identity, $member->fresh()->only(['email', 'password', 'access_enabled']));
        $this->assertSame('active', $this->membership($member)->status);
        $this->assertSame($person->id, $this->membership($member)->person_id);
    }

    public function test_inactive_company_keeps_relations_but_refuses_changes(): void
    {
        $joao = $this->saveJoao([$this->express->id => ['customer' => '1']]);
        $this->express->forceFill(['active' => false])->saveOrFail();

        $count = AuditRecord::count();
        $this->actingAs($this->owner)
            ->patchJson('/conta/pessoas/'.$joao->id, [
                'name' => 'João da Silva',
                'relations' => [$this->express->id => ['customer' => '0']],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('_form');

        $this->assertTrue(Customer::sole()->active);
        $this->assertSame($count, AuditRecord::count());
        $this->getJson('/conta/pessoas/'.$joao->id.'/visualizar')
            ->assertJsonPath('relations.0.customer', 'active')
            ->assertJsonPath('relations.0.company_active', false);
    }

    public function test_migration_assigns_the_single_company_and_derives_commercial_tags(): void
    {
        (require database_path('migrations/2026_11_21_000002_add_commercial_tags.php'))->down();
        $migration = require database_path('migrations/2026_11_21_000001_add_company_to_commercial_relations.php');
        $account = $this->account('Conta de uma empresa');
        $company = $this->company($account, 'Empresa única', '13131313');
        $this->administrator($account);
        $person = Person::create(['account_id' => $account->id, 'name' => 'Cliente antigo']);
        Customer::create([
            'account_id' => $account->id,
            'company_id' => $company->id,
            'person_id' => $person->id,
            'active' => true,
        ]);
        Supplier::create([
            'account_id' => $account->id,
            'company_id' => $company->id,
            'person_id' => $person->id,
            'active' => false,
        ]);

        // A reversão sem perda regrava a etiqueta da relação ativa e volta ao modelo de uma relação por pessoa.
        $migration->down();
        $this->assertDatabaseHas('person_tag', [
            'person_id' => $person->id,
            'tag_id' => Tag::customerTag($account->id)->id,
        ]);
        $this->assertDatabaseMissing('person_tag', [
            'person_id' => $person->id,
            'tag_id' => Tag::supplierTag($account->id)->id,
        ]);
        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('customers', 'company_id'));

        $migration->up();
        $this->assertSame($company->id, (int) DB::table('customers')->where('person_id', $person->id)->value('company_id'));
        $this->assertSame($company->id, (int) DB::table('suppliers')->where('person_id', $person->id)->value('company_id'));
        $this->assertFalse((bool) DB::table('suppliers')->where('person_id', $person->id)->value('active'));
        $this->assertDatabaseMissing('person_tag', ['person_id' => $person->id]);
        $this->artisan('audit:check')->assertSuccessful();
    }

    public function test_migration_never_guesses_a_company_and_down_refuses_to_lose_relations(): void
    {
        (require database_path('migrations/2026_11_21_000002_add_commercial_tags.php'))->down();
        $migration = require database_path('migrations/2026_11_21_000001_add_company_to_commercial_relations.php');
        $joao = $this->saveJoao([
            $this->roupas->id => ['customer' => '1'],
            $this->express->id => ['customer' => '1'],
        ]);

        try {
            $migration->down();
            $this->fail('A reversão deveria recusar uma pessoa com relações em duas empresas.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('restaure o backup', $exception->getMessage());
        }

        $this->assertDatabaseCount('customers', 2);

        // Sem a relação repetida, a reversão é aceita; com várias empresas, a reaplicação não escolhe uma delas.
        DB::table('customers')->where('company_id', $this->express->id)->delete();
        $migration->down();

        try {
            $migration->up();
            $this->fail('A migração deveria parar numa conta com várias empresas.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString((string) $this->gartan->id, $exception->getMessage());
            $this->assertStringContainsString('Nenhuma empresa é escolhida automaticamente', $exception->getMessage());
        }

        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('customers', 'company_id'));
        $this->assertSame(1, DB::table('customers')->where('person_id', $joao->id)->count());
    }

    public function test_migration_stops_when_a_commercial_tag_has_no_active_relation(): void
    {
        (require database_path('migrations/2026_11_21_000002_add_commercial_tags.php'))->down();
        $migration = require database_path('migrations/2026_11_21_000001_add_company_to_commercial_relations.php');
        $account = $this->account('Conta incoerente');
        $this->company($account, 'Empresa', '14141414');
        $this->administrator($account);
        $migration->down();
        $person = Person::create(['account_id' => $account->id, 'name' => 'Etiqueta sem relação']);
        DB::table('person_tag')->insert([
            'person_id' => $person->id,
            'tag_id' => Tag::supplierTag($account->id)->id,
        ]);

        try {
            $migration->up();
            $this->fail('A etiqueta sem relação deveria interromper a migração.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString((string) $person->id, $exception->getMessage());
        }

        $this->assertDatabaseHas('person_tag', ['person_id' => $person->id]);
    }
}
