<?php

namespace Tests\Feature;

use App\Domains\Acessos\Application\Actions\DefaultRolesAction;
use App\Domains\Acessos\Application\Actions\RegisterAccountMembershipAction;
use App\Domains\Acessos\Application\Context\OrganizationContext;
use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Acessos\Domain\DTOs\AuthorizedNodes;
use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Acessos\Domain\Models\RoleAssignment;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Organizacao\Application\Actions\CreateCompanyAction;
use App\Domains\Organizacao\Application\Actions\SaveEstablishmentAction;
use App\Domains\Organizacao\Application\Data\SaveCompanyData;
use App\Domains\Organizacao\Application\Data\SaveEstablishmentData;
use App\Domains\Organizacao\Domain\DTOs\OrganizationCode;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * E06: contexto organizacional na URL. O módulo de estoque e produtos existe só neste teste (cenários 17 a 20 e 24).
 */
final class OrganizationContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('fixture_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id');
            $table->foreignId('company_id');
            $table->foreignId('establishment_id');
            $table->string('product');
            $table->integer('quantity');
        });
        Schema::create('fixture_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id');
            $table->foreignId('company_id');
            $table->string('name');
        });

        $this->fixtureRoutes();
    }

    public function test_urls_resolve_the_tenant_company_and_establishment_by_code(): void
    {
        $gartan = $this->gartan();
        $ana = $this->ana($gartan);
        $roupas = $gartan['units']['roupas-janauba'];

        $this->actingAs($ana)->getJson('/conta/estabelecimento/'.$roupas->code)
            ->assertOk()
            ->assertJsonPath('establishment.code', $roupas->code)
            ->assertJsonPath('establishment.company_name', 'Gartan Roupas')
            ->assertJsonPath('organization_context.mode', 'establishment')
            ->assertJsonPath('organization_context.company.code', $gartan['companies']['roupas']->code)
            ->assertJsonPath('organization_context.establishment.code', $roupas->code);

        $this->getJson('/conta/empresa/'.$gartan['companies']['roupas']->code.'/produtos')
            ->assertOk()
            ->assertJsonPath('mode', 'company')
            ->assertJsonPath('names', ['Camisa']);

        // Fora dos grupos com contexto, o serviço representa a operação administrativa da conta da URL.
        $this->assertTrue($this->enterAccount($ana));
        $context = app(OrganizationContext::class);
        $this->assertSame('tenant', $context->mode());
        $this->assertSame($gartan['account']->id, $context->accountId());
        $this->assertSame($this->membership($ana)->id, $context->membershipId($ana));
        $this->assertTrue($context->node() == AccessNode::tenant($gartan['account']->id));
    }

    public function test_the_selector_lists_only_the_contexts_covered_by_assignments(): void
    {
        $gartan = $this->gartan();
        $ana = $this->ana($gartan);
        $units = $gartan['units'];

        $selector = $this->actingAs($ana)->getJson('/conta/estabelecimento/'.$units['roupas-guanambi']->code)
            ->assertOk()
            ->json('organization_context.establishments');

        // Empresa inteira na Gartan Roupas e só Janaúba na Gartan Express; nada da Gartan 3.
        $this->assertSame(
            [$units['express-janauba']->code, $units['roupas-guanambi']->code, $units['roupas-janauba']->code],
            array_column($selector, 'code'),
        );
        $this->assertSame(['Gartan Express', 'Gartan Roupas', 'Gartan Roupas'], array_column($selector, 'company_name'));
        $this->assertSame([false, true, false], array_column($selector, 'primary'));

        // A atribuição da empresa cobre uma unidade criada depois.
        $new = $this->unit($gartan['companies']['roupas'], 'Caetité');
        $this->getJson('/conta/estabelecimento/'.$new->code)->assertOk();

        foreach (['express-guanambi', 'express-jequie', 'gartan3'] as $hidden) {
            $this->getJson('/conta/estabelecimento/'.$units[$hidden]->code)
                ->assertNotFound()
                ->assertJsonMissing(['name' => $units[$hidden]->name]);
        }

        // Unidade de estabelecimento não alcança o contexto da empresa (herança só descendente).
        $this->getJson('/conta/empresa/'.$gartan['companies']['express']->code.'/produtos')->assertNotFound();
    }

    public function test_access_to_a_context_does_not_grant_operations(): void
    {
        $gartan = $this->gartan();
        $ana = $this->ana($gartan);
        $janauba = $gartan['units']['express-janauba'];
        $stock = $this->stock($janauba);

        // O cargo de operador lê o estoque, mas não grava nem vê o cadastro da unidade para edição.
        $this->actingAs($ana)->getJson('/conta/estabelecimento/'.$janauba->code.'/estoque/'.$stock)
            ->assertOk()
            ->assertJsonPath('product', 'Produto '.$janauba->name);
        $this->patchJson('/conta/estabelecimento/'.$janauba->code.'/estoque/'.$stock, ['quantity' => 99])
            ->assertForbidden();
        $this->getJson('/conta/estabelecimento/'.$janauba->code)
            ->assertOk()
            ->assertJsonPath('establishment.can_update', false);

        $this->assertDatabaseHas('fixture_stock', ['id' => $stock, 'quantity' => 10]);
        $this->assertTrue($this->enterAccount($ana));
        $this->assertTrue($ana->can('account.establishments.view', $this->node($janauba)));
        $this->assertFalse($ana->can('account.establishments.update', $this->node($janauba)));
    }

    public function test_url_manipulation_and_other_tenants_answer_not_found_without_data(): void
    {
        $gartan = $this->gartan();
        $ana = $this->ana($gartan);
        $other = $this->account('Outra conta');
        $outside = $this->company($other, 'Outra Empresa', '44555666')->primaryEstablishment()->sole();
        $guanambi = $gartan['units']['roupas-guanambi'];
        $foreignStock = $this->stock($outside);
        $hiddenStock = $this->stock($gartan['units']['express-jequie']);

        // O mesmo código em outro tenant resolve só dentro da conta do usuário.
        DB::table('establishments')->where('id', $outside->id)->update(['code' => $guanambi->code]);

        $this->actingAs($ana)->getJson('/conta/estabelecimento/'.$guanambi->code)
            ->assertOk()
            ->assertJsonPath('establishment.name', $guanambi->name)
            ->assertJsonMissing(['company_name' => 'Outra Empresa']);

        foreach (['inexistente', 'Maiuscula', 'a', str_repeat('a', 41)] as $code) {
            $this->getJson('/conta/estabelecimento/'.$code)->assertNotFound();
        }

        // A abertura direta recebe só o HTML inicial; os dados e a recusa vêm na requisição JSON da aplicação.
        $hidden = $gartan['units']['gartan3'];
        $this->get('/conta/estabelecimento/'.$hidden->code)
            ->assertOk()
            ->assertViewIs('app')
            ->assertDontSee('Gartan 3');
        $this->getJson('/conta/estabelecimento/'.$hidden->code)->assertNotFound();

        // Identificadores de outra unidade ou de outro tenant não atravessam o contexto da URL.
        $this->getJson('/conta/estabelecimento/'.$guanambi->code.'/estoque/'.$foreignStock)->assertNotFound();
        $this->getJson('/conta/estabelecimento/'.$guanambi->code.'/estoque/'.$hiddenStock)->assertNotFound();
        $this->patchJson('/conta/estabelecimento/'.$guanambi->code.'/estoque/'.$hiddenStock, ['quantity' => 1])
            ->assertNotFound();
        $this->getJson('/conta/empresa/'.Company::where('account_id', $other->id)->sole()->code.'/produtos')->assertNotFound();

        $this->assertDatabaseHas('fixture_stock', ['id' => $hiddenStock, 'quantity' => 10]);
        $this->assertDatabaseHas('fixture_stock', ['id' => $foreignStock, 'quantity' => 10]);
    }

    public function test_revocation_suspension_and_inactivation_apply_on_the_next_request(): void
    {
        $gartan = $this->gartan();
        $ana = $this->ana($gartan);
        $janauba = $gartan['units']['express-janauba'];
        $guanambi = $gartan['units']['roupas-guanambi'];
        $membership = $this->membership($ana);

        $this->actingAs($ana)->getJson('/conta/estabelecimento/'.$janauba->code)->assertOk();

        $membership->assignments()->where('establishment_id', $janauba->id)->delete();

        $this->getJson('/conta/estabelecimento/'.$janauba->code)->assertNotFound();
        $this->getJson('/conta/estabelecimento/'.$guanambi->code)
            ->assertOk()
            ->assertJsonMissing(['code' => $janauba->code]);

        // Unidade inativa: escrita e navegação recusadas com orientação; o histórico declarado continua legível.
        $janaubaRoupas = $gartan['units']['roupas-janauba'];
        DB::table('establishments')->where('id', $janaubaRoupas->id)->update(['active' => false]);

        $this->getJson('/conta/estabelecimento/'.$janaubaRoupas->code)
            ->assertForbidden()
            ->assertJsonPath('message', 'Este estabelecimento está inativo. Escolha outro no seletor.');
        $this->getJson('/conta/estabelecimento/'.$janaubaRoupas->code.'/historico')->assertOk();
        $this->postJson('/conta/estabelecimento/'.$janaubaRoupas->code.'/historico')->assertForbidden();
        $this->getJson('/conta/estabelecimento/'.$guanambi->code)
            ->assertOk()
            ->assertJsonMissing(['code' => $janaubaRoupas->code]);

        // Empresa inativa leva junto todas as unidades dela.
        DB::table('companies')->where('id', $gartan['companies']['roupas']->id)->update(['active' => false]);
        $this->getJson('/conta/estabelecimento/'.$guanambi->code)->assertForbidden();
        $this->getJson('/conta/empresa/'.$gartan['companies']['roupas']->code.'/produtos')->assertForbidden();
        $this->getJson('/conta/estabelecimento')->assertForbidden();

        // Vínculo suspenso: nenhuma rota de contexto responde.
        DB::table('companies')->where('id', $gartan['companies']['roupas']->id)->update(['active' => true]);
        $membership->forceFill(['status' => 'suspended'])->saveOrFail();
        $this->getJson('/conta/estabelecimento/'.$guanambi->code)->assertForbidden();
        $this->getJson('/conta/estabelecimento')->assertForbidden()
            ->assertJsonPath('message', 'Seu acesso a esta conta está suspenso.');
    }

    public function test_full_and_local_administrators_see_their_own_scope(): void
    {
        $gartan = $this->gartan();
        $owner = User::factory()->asAccountAdministrator($gartan['account']->id)->create();
        $local = User::factory()->forAccount($gartan['account']->id)->create();
        $janauba = $gartan['units']['express-janauba'];
        $this->replaceAssignments($local, [
            [$this->role($gartan['account'], 'Administrador da unidade', [
                'account.establishments.view',
                'account.establishments.update',
                'account.users.assign-role',
            ]), $this->node($janauba)],
        ]);

        $all = $this->actingAs($owner)->getJson('/conta')->assertOk()->json('organization_context');
        $this->assertSame('tenant', $all['mode']);
        $this->assertCount(6, $all['establishments']);
        $this->getJson('/conta/estabelecimento/todos/estoque')
            ->assertOk()
            ->assertJsonPath('total', 6);

        // Administrador local: uma unidade só, sem seletor e sem acesso às demais.
        $this->actingAs($local)->getJson('/conta/estabelecimento/'.$janauba->code)
            ->assertOk()
            ->assertJsonPath('organization_context.establishments', [])
            ->assertJsonPath('establishment.can_update', true);
        $this->getJson('/conta/estabelecimento/'.$gartan['units']['express-guanambi']->code)->assertNotFound();
        $this->get('/conta/estabelecimento')->assertRedirect($this->accountPath('/conta/estabelecimento/'.$janauba->code));
        $this->getJson('/conta/estabelecimento/todos/estoque')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.establishment_id', $janauba->id);
    }

    public function test_a_single_unit_account_needs_no_selector_and_redirects_without_code(): void
    {
        $account = $this->account('Loja única');
        $unit = $this->company($account, 'Loja Única', '12345678')->primaryEstablishment()->sole();
        $owner = User::factory()->asAccountAdministrator($account->id)->create();

        $this->actingAs($owner)->getJson('/conta')
            ->assertOk()
            ->assertJsonPath('organization_context.mode', 'tenant')
            ->assertJsonPath('organization_context.establishment', null)
            ->assertJsonPath('organization_context.establishments', []);

        $this->get('/conta/estabelecimento')->assertRedirect($this->accountPath('/conta/estabelecimento/'.$unit->code));
        $this->get('/conta/estabelecimento?para=estoque')->assertRedirect($this->accountPath('/conta/estabelecimento/'.$unit->code.'/estoque'));
        $this->get('/conta/estabelecimento?para=historico')->assertRedirect($this->accountPath('/conta/estabelecimento/'.$unit->code.'/historico'));
        $this->get('/conta/estabelecimento?para=../../saas')->assertNotFound();
        $this->get('/conta/estabelecimento?para=inexistente')->assertNotFound();

        // Operações administrativas da conta continuam sem empresa nem estabelecimento.
        $this->getJson('/conta/editar')
            ->assertOk()
            ->assertJsonPath('organization_context.mode', 'tenant')
            ->assertJsonPath('organization_context.company', null);
        $this->getJson('/conta/organizacao')->assertOk()->assertJsonPath('organization_context.mode', 'tenant');
    }

    public function test_consolidated_view_is_limited_to_supporting_modules_and_authorized_units(): void
    {
        $gartan = $this->gartan();
        $ana = $this->ana($gartan);

        $rows = $this->actingAs($ana)->getJson('/conta/estabelecimento/todos/estoque')
            ->assertOk()
            ->assertJsonPath('mode', 'consolidated')
            ->assertJsonPath('total', 3)
            ->json('rows');

        $this->assertEqualsCanonicalizing(
            [
                $gartan['units']['roupas-guanambi']->id,
                $gartan['units']['roupas-janauba']->id,
                $gartan['units']['express-janauba']->id,
            ],
            array_column($rows, 'establishment_id'),
        );

        // Módulo sem suporte declarado e escrita no consolidado são recusados.
        $this->getJson('/conta/estabelecimento/todos')->assertOk()->assertJsonCount(3, 'workspace');
        $this->getJson('/conta/estabelecimento/todos/historico')->assertNotFound();
        $this->patchJson('/conta/estabelecimento/todos/estoque/1', ['quantity' => 1])->assertForbidden();
        $this->getJson('/conta/empresa/todos/produtos')->assertNotFound();

        $page = $this->getJson('/conta/estabelecimento/'.$gartan['units']['roupas-guanambi']->code.'/estoque')
            ->assertOk()
            ->assertJsonPath('total', 1);
        $this->assertTrue($page->json('consolidated'));
    }

    public function test_two_tabs_keep_their_own_context_for_reads_and_writes(): void
    {
        $gartan = $this->gartan();
        $ana = $this->ana($gartan);
        $tabOne = $gartan['units']['roupas-guanambi'];
        $tabTwo = $gartan['units']['express-janauba'];
        $sameCompany = $gartan['units']['roupas-janauba'];
        $first = $this->stock($tabOne);
        $second = $this->stock($tabTwo);
        $sibling = $this->stock($sameCompany);
        $this->replaceAssignments($ana, [
            [$this->role($gartan['account'], 'Gerente', [
                'account.establishments.view',
                'account.establishments.update',
            ]), AccessNode::company($gartan['account']->id, $gartan['companies']['roupas']->id)],
            [$this->role($gartan['account'], 'Operador com escrita', [
                'account.establishments.view',
                'account.establishments.update',
            ]), $this->node($tabTwo)],
        ]);

        // A aba 2 abre outra empresa, e a aba 1 continua na sua unidade.
        // Cada unidade tem o registro inicial do cenário e o registro criado neste teste.
        $this->actingAs($ana)->getJson('/conta/estabelecimento/'.$tabOne->code.'/estoque')
            ->assertJsonPath('total', 2)
            ->assertJsonPath('rows.1.id', $first);
        $this->getJson('/conta/estabelecimento/'.$tabTwo->code.'/estoque')
            ->assertJsonPath('total', 2)
            ->assertJsonPath('rows.1.id', $second);
        $this->patchJson('/conta/estabelecimento/'.$tabOne->code.'/estoque/'.$first, ['quantity' => 5])
            ->assertOk()
            ->assertJsonPath('establishment_id', $tabOne->id);

        // Modal da aba 1 com o registro da aba 2 e escrita cruzada entre unidades da mesma empresa.
        $this->getJson('/conta/estabelecimento/'.$tabOne->code.'/estoque/'.$second)->assertNotFound();
        $this->patchJson('/conta/estabelecimento/'.$tabOne->code.'/estoque/'.$sibling, ['quantity' => 7])
            ->assertNotFound();
        $this->getJson('/conta/estabelecimento/'.$sameCompany->code)->assertOk();
        $this->patchJson('/conta/estabelecimento/'.$tabTwo->code.'/estoque/'.$second, ['quantity' => 3])->assertOk();

        $this->assertDatabaseHas('fixture_stock', ['id' => $first, 'quantity' => 5]);
        $this->assertDatabaseHas('fixture_stock', ['id' => $second, 'quantity' => 3]);
        $this->assertDatabaseHas('fixture_stock', ['id' => $sibling, 'quantity' => 10]);

        // Sem padrão, a entrada usa a primeira unidade navegável.
        $this->get('/conta/estabelecimento')->assertRedirect($this->accountPath('/conta/estabelecimento/'.$tabTwo->code));
    }

    public function test_workspace_default_is_personal_and_temporary_tabs_keep_their_urls(): void
    {
        $gartan = $this->gartan();
        $ana = $this->ana($gartan);
        $units = $gartan['units'];
        $base = '/conta/estabelecimento';
        $preference = '/conta/area-de-trabalho/padrao';
        $first = [$units['express-janauba']->code, $units['roupas-guanambi']->code];
        $second = [$units['roupas-guanambi']->code, $units['roupas-janauba']->code];
        $firstTab = $base.'/todos?unidades='.implode(',', $first);
        $secondTab = $base.'/todos?unidades='.implode(',', $second);

        $this->actingAs($ana)->putJson($preference, ['unidades' => $first])->assertNoContent();
        $this->get($base)->assertRedirect($this->accountPath($firstTab));
        $this->getJson($secondTab)->assertOk()
            ->assertJsonPath('organization_context.selected_codes', $second)
            ->assertJsonCount(3, 'organization_context.establishments')
            ->assertJsonCount(2, 'workspace');
        $this->getJson($firstTab)->assertOk()->assertJsonPath('organization_context.selected_codes', $first);
        $this->getJson($secondTab)->assertOk()->assertJsonPath('organization_context.selected_codes', $second);
        $this->getJson($preference)->assertJsonPath('codes', $first);
        $this->get($base)->assertRedirect($this->accountPath($firstTab));

        $this->putJson($preference, ['unidades' => $second])->assertNoContent();
        $this->getJson($firstTab)->assertOk()->assertJsonPath('organization_context.selected_codes', $first);
        $this->get($base)->assertRedirect($this->accountPath($secondTab));

        $owner = User::factory()->asAccountAdministrator($gartan['account']->id)->create();
        $this->actingAs($owner)->getJson($preference)->assertJsonCount(1, 'codes');
        $this->actingAs($ana)->getJson($preference)->assertJsonPath('codes', $second);
    }

    public function test_workspace_preference_is_separate_for_each_tenant_of_the_same_user(): void
    {
        $gartan = $this->gartan();
        $ana = $this->ana($gartan);
        $account = $this->account('Segunda conta');
        $company = $this->company($account, 'Segunda Empresa', '44555666');
        $first = $company->primaryEstablishment()->sole();
        $second = $this->unit($company, 'Filial');
        [$administrator] = app(DefaultRolesAction::class)->execute($account->id);
        app(RegisterAccountMembershipAction::class)->execute($ana, $administrator, AccessNode::tenant($account->id));

        // E09: cada conta da URL usa o próprio vínculo e o próprio padrão.
        $gartanPreference = '/contas/'.$gartan['account']->code.'/area-de-trabalho/padrao';
        $secondPreference = '/contas/'.$account->code.'/area-de-trabalho/padrao';
        $original = [$gartan['units']['roupas-guanambi']->code, $gartan['units']['roupas-janauba']->code];
        $this->actingAs($ana)->putJson($gartanPreference, ['unidades' => $original])->assertNoContent();

        $this->getJson($secondPreference)->assertJsonPath('codes', [$first->code]);
        $this->putJson($secondPreference, ['unidades' => [$second->code]])->assertNoContent();
        $this->getJson($secondPreference)->assertJsonPath('codes', [$second->code]);
        // Unidade de uma conta nunca entra no padrão da outra.
        $this->putJson($secondPreference, ['unidades' => $original])->assertNotFound();

        $this->getJson($gartanPreference)->assertJsonPath('codes', $original);
    }

    public function test_workspace_intersects_selection_and_operation_permissions_and_revalidates_revocation(): void
    {
        $gartan = $this->gartan();
        $ana = $this->ana($gartan);
        $units = $gartan['units'];
        $guanambi = $units['roupas-guanambi'];
        $otherCompany = $units['express-janauba'];
        $codes = [$guanambi->code, $otherCompany->code];
        $base = '/conta/estabelecimento';
        $preference = '/conta/area-de-trabalho/padrao';
        $query = '?unidades='.implode(',', $codes);
        $stock = $this->stock($guanambi);

        $this->actingAs($ana)->putJson($preference, ['unidades' => []])->assertUnprocessable();
        $this->putJson($preference, ['unidades' => [$guanambi->code, $units['gartan3']->code]])->assertNotFound();
        $this->putJson($preference, ['unidades' => $codes])->assertNoContent();
        $this->getJson($base.'/todos/estoque'.$query)->assertOk()->assertJsonPath('total', 3);
        $this->getJson($base.'/todos/estoque?unidades='.$units['gartan3']->code)->assertNotFound();
        $this->getJson($base.'/todos/estoque?unidades[]=x')->assertNotFound();
        $this->patchJson($base.'/todos/estoque/'.$stock.$query, ['quantity' => 4])->assertForbidden();
        $this->patchJson($base.'/'.$guanambi->code.'/estoque/'.$stock.$query, ['quantity' => 4])
            ->assertOk()->assertJsonPath('establishment_id', $guanambi->id);
        $this->assertDatabaseHas('fixture_stock', ['id' => $stock, 'quantity' => 4]);

        $localRole = $this->role($gartan['account'], 'Operador sem consulta', ['account.establishments.update']);
        $membership = $this->membership($ana);
        $membership->assignments()->where('establishment_id', $otherCompany->id)->delete();
        $assignment = RoleAssignment::grant($membership, $localRole, $this->node($otherCompany), null);
        $assignment->saveOrFail();
        $this->getJson($base.'/todos/estoque'.$query)->assertOk()->assertJsonPath('total', 2);

        $assignment->delete();
        $this->getJson($base.'/todos/estoque'.$query)->assertNotFound();
        $this->getJson($preference)->assertJsonPath('codes', [$guanambi->code]);
        $this->get($base)->assertRedirect($this->accountPath($base.'/'.$guanambi->code));
    }

    public function test_workspace_entry_uses_the_tab_selection_and_only_compatible_destinations(): void
    {
        $gartan = $this->gartan();
        $ana = $this->ana($gartan);
        $units = $gartan['units'];
        $guanambi = $units['roupas-guanambi']->code;
        $janauba = $units['express-janauba']->code;
        $tab = $guanambi.','.$janauba;
        $base = '/conta/estabelecimento';

        // O padrão salvo é outro: a entrada respeita a seleção enviada pela aba e não grava nada.
        $this->actingAs($ana)->putJson('/conta/area-de-trabalho/padrao', ['unidades' => [$guanambi]])->assertNoContent();
        $this->get($base.'?unidades='.$tab)->assertRedirect($this->accountPath($base.'/todos?unidades='.$tab));
        $this->getJson($base.'?unidades='.$tab)->assertOk()->assertJsonPath('redirect', url($this->accountPath($base.'/todos?unidades='.$tab)));
        $this->get($base)->assertRedirect($this->accountPath($base.'/'.$guanambi));
        $this->assertSame(json_encode([$guanambi]), $this->membership($ana)->getAttribute('workspace_establishments'));

        // Destino com consolidado preserva a seleção; com uma unidade, abre direto.
        $this->get($base.'?unidades='.$tab.'&para=estoque')->assertRedirect($this->accountPath($base.'/todos/estoque?unidades='.$tab));
        $this->get($base.'?unidades='.$janauba.'&para=historico')->assertRedirect($this->accountPath($base.'/'.$janauba.'/historico'));

        // Destino de uma unidade com várias selecionadas: pede a escolha, sem escolher pelo usuário.
        $chooser = $base.'/todos?unidades='.$tab.'&para=historico';
        $this->get($base.'?unidades='.$tab.'&para=historico')->assertRedirect($this->accountPath($chooser));
        $this->get($base.'?unidades='.$tab.'&para=estoque/1')
            ->assertRedirect($this->accountPath($base.'/todos?unidades='.$tab.'&para=estoque/1'));
        $this->getJson($chooser)->assertOk()
            ->assertJsonPath('destination', 'historico')
            ->assertJsonCount(2, 'workspace');

        // URLs diretas incompatíveis, destinos arbitrários e seleções indisponíveis continuam recusados.
        $this->getJson($base.'/todos/historico?unidades='.$tab)->assertNotFound();
        $this->getJson($base.'/todos?unidades='.$tab.'&para=inexistente')->assertNotFound();
        foreach (['https://externo.test', '//externo.test', '../../saas/contas', 'estoque?x=1', 'inexistente'] as $path) {
            $this->get($base.'?unidades='.$tab.'&para='.urlencode($path))->assertNotFound();
        }
        $this->get($base.'?unidades='.$guanambi.','.$units['gartan3']->code)->assertNotFound();
        $this->get($base.'?unidades=')->assertNotFound();

        // A revogação vale na requisição seguinte, mesmo para a seleção guardada na aba.
        $this->membership($ana)->assignments()->where('establishment_id', $units['express-janauba']->id)->delete();
        $this->get($base.'?unidades='.$tab)->assertNotFound();
        $this->get($base.'?unidades='.$guanambi.'&para=estoque')->assertRedirect($this->accountPath($base.'/'.$guanambi.'/estoque'));
    }

    public function test_inspection_follows_the_represented_user_and_ends_on_revocation(): void
    {
        $gartan = $this->gartan();
        $ana = $this->ana($gartan);
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $janauba = $gartan['units']['express-janauba'];
        $stock = $this->stock($gartan['units']['roupas-guanambi']);
        $start = route('saas.accounts.inspection.start', $gartan['account']);

        $this->actingAs($platform)->withSession(['auth.password_confirmed_at' => time()])
            ->post($start, ['user_id' => $ana->id, 'mode' => 'read-only'])
            ->assertRedirect(route('account.dashboard', ['conta' => $gartan['account']->code]));

        $this->getJson('/conta/estabelecimento/'.$janauba->code)
            ->assertOk()
            ->assertJsonCount(3, 'organization_context.establishments')
            ->assertJsonPath('auth.inspection_mode', 'read-only');
        $this->getJson('/conta/estabelecimento/'.$gartan['units']['gartan3']->code)->assertNotFound();
        $this->patchJson('/conta/estabelecimento/'.$gartan['units']['roupas-guanambi']->code.'/estoque/'.$stock, [
            'quantity' => 1,
        ])->assertForbidden();
        $this->assertDatabaseHas('fixture_stock', ['id' => $stock, 'quantity' => 10]);
        $this->putJson('/conta/area-de-trabalho/padrao', ['unidades' => [$janauba->code]])->assertForbidden();
        $selection = $janauba->code.','.$gartan['units']['roupas-guanambi']->code;
        $this->get('/conta/estabelecimento?unidades='.$selection)
            ->assertRedirect($this->accountPath('/conta/estabelecimento/todos?unidades='.$selection));
        $this->get('/conta/estabelecimento?unidades='.$gartan['units']['gartan3']->code)->assertNotFound();
        $this->assertNull($this->membership($ana)->getAttribute('workspace_establishments'));

        $platform->forceFill(['is_saas_admin' => false])->saveOrFail();

        $this->getJson('/conta/estabelecimento/'.$janauba->code)->assertUnauthorized();
        $this->assertGuest();
        $this->getJson('/conta/estabelecimento/'.$janauba->code)->assertUnauthorized();
    }

    public function test_edit_inspection_writes_in_the_url_context_with_the_real_author(): void
    {
        $gartan = $this->gartan();
        $ana = $this->ana($gartan);
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $guanambi = $gartan['units']['roupas-guanambi'];
        $stock = $this->stock($guanambi);

        $this->actingAs($platform)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('saas.accounts.inspection.start', $gartan['account']), ['user_id' => $ana->id, 'mode' => 'edit']);

        $this->patchJson('/conta/estabelecimento/'.$guanambi->code.'/estoque/'.$stock, ['quantity' => 4])
            ->assertOk()
            ->assertJsonPath('actor_id', $platform->id)
            ->assertJsonPath('membership_id', $this->membership($ana)->id);
        $this->assertDatabaseHas('fixture_stock', ['id' => $stock, 'quantity' => 4]);
    }

    public function test_consecutive_requests_do_not_share_context_between_accounts(): void
    {
        $gartan = $this->gartan();
        $ana = $this->ana($gartan);
        $other = $this->account('Outra conta');
        $outside = $this->company($other, 'Outra Empresa', '44555666')->primaryEstablishment()->sole();
        $this->unit(Company::where('account_id', $other->id)->sole(), 'Segunda loja');
        $owner = User::factory()->asAccountAdministrator($other->id)->create();
        $guanambi = $gartan['units']['roupas-guanambi'];
        $this->stock($outside);

        for ($round = 0; $round < 3; $round++) {
            $this->actingAs($ana)->getJson('/conta/estabelecimento/'.$guanambi->code.'/estoque')
                ->assertOk()
                ->assertJsonPath('total', 1)
                ->assertJsonPath('rows.0.establishment_id', $guanambi->id);
            $this->getJson('/conta/estabelecimento/'.$outside->code)->assertNotFound();

            $this->actingAs($owner)->getJson('/conta/estabelecimento/'.$outside->code.'/estoque')
                ->assertOk()
                ->assertJsonPath('rows.0.establishment_id', $outside->id);
            $this->getJson('/conta/estabelecimento/'.$outside->code)
                ->assertOk()
                ->assertJsonCount(2, 'organization_context.establishments')
                ->assertJsonMissing(['company_name' => 'Gartan Roupas']);
            $this->getJson('/conta/estabelecimento/'.$guanambi->code)->assertNotFound();
            $this->getJson('/conta/estabelecimento/todos/estoque')->assertJsonPath('total', 1);
        }
    }

    public function test_audit_and_help_center_keep_the_rbac_scope_inside_contexts(): void
    {
        $gartan = $this->gartan();
        $ana = $this->ana($gartan);
        $janauba = $gartan['units']['express-janauba'];
        $jequie = $gartan['units']['express-jequie'];
        $role = $this->role($gartan['account'], 'Auditor da unidade', [
            'account.audit.list',
            'account.audit.view',
            'self.help.list',
            'self.help.view',
        ]);
        RoleAssignment::grant($this->membership($ana), $role, $this->node($janauba), null)->saveOrFail();

        foreach ([$janauba, $jequie] as $unit) {
            AuditLog::record(
                'establishment',
                $unit->id,
                $gartan['account']->id,
                'updated',
                'Evento '.$unit->name,
                ['name' => ['label' => 'Nome', 'before' => 'A', 'after' => 'B']],
                companyId: $unit->company_id,
                establishmentId: $unit->id,
            );
        }

        $this->actingAs($ana)->getJson('/conta/estabelecimento/'.$janauba->code)->assertOk();
        $records = $this->getJson('/conta/auditoria?search=Evento')->assertOk();
        $records->assertJsonPath('records.total', 1)->assertJsonPath('records.data.0.name', 'Evento '.$janauba->name);
        $this->assertSame('tenant', $records->json('organization_context.mode'));

        $help = $this->getJson('/conta/ajuda/estabelecimento-atual')->assertOk();
        // Na conta (E09), a ajuda é uma tela administrativa: sem empresa nem unidade da URL.
        $this->assertSame('tenant', $help->json('organization_context.mode'));
        $this->assertNull($help->json('organization_context.establishment'));
        $this->assertStringContainsString('não muda as outras abas', $help->json('article.html'));
    }

    public function test_the_selector_list_costs_one_query_regardless_of_the_number_of_units(): void
    {
        $account = $this->account('Rede');
        $owner = User::factory()->asAccountAdministrator($account->id)->create();
        $companies = [];

        foreach (range(1, 5) as $index) {
            $companies[] = $this->company($account, 'Empresa '.$index, str_pad((string) (10000000 + $index), 8, '0'));
        }

        $counts = [];
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        foreach ([[1, 5], [10, 50]] as [$perCompany, $expected]) {
            foreach ($companies as $company) {
                while ($company->establishments()->count() < $perCompany) {
                    $this->unit($company, 'Loja '.$company->id.'-'.$company->establishments()->count());
                }
            }

            $code = Establishment::where('account_id', $account->id)->orderBy('id')->value('code');
            $queries = [];

            $response = $this->actingAs($owner)->getJson('/conta/estabelecimento/'.$code)->assertOk();

            $this->assertCount($expected, $response->json('organization_context.establishments'));
            $selector = array_filter($queries, fn (string $sql) => str_contains($sql, 'from "establishments" as "e"'));
            // Uma consulta resolve o código da URL e outra enumera o seletor, sem laço por unidade.
            $this->assertCount(2, $selector);
            $this->assertCount(count(array_unique($queries)), $queries, implode("\n", $queries));
            $counts[] = count($queries);
        }

        $this->assertSame($counts[0], $counts[1]);
    }

    private function fixtureRoutes(): void
    {
        $stock = function (Request $request, OrganizationContext $context): array {
            $nodes = $context->scopeFor($request->user(), 'account.establishments.view');
            abort_if($nodes->isEmpty(), 403);

            $query = DB::table('fixture_stock')->where('account_id', $context->accountId());
            $this->applyNodes($query, $nodes);
            $rows = $query->orderBy('id')->get(['id', 'establishment_id', 'product', 'quantity']);

            return [
                'mode' => $context->mode(),
                'consolidated' => $context->supportsConsolidated(),
                'total' => $rows->count(),
                'rows' => $rows->all(),
            ];
        };

        $record = function (Request $request, OrganizationContext $context) {
            $user = $request->user();
            abort_unless($user->can('account.establishments.view', $context->node()), 403);

            return DB::table('fixture_stock')
                ->where('account_id', $context->accountId())
                ->where('establishment_id', $context->establishment()?->id)
                ->where('id', $request->route('stockId'))
                ->firstOrFail();
        };

        $update = function (Request $request, OrganizationContext $context) {
            $user = $request->user();
            abort_unless($user->can('account.establishments.update', $context->node()), 403);

            $updated = DB::table('fixture_stock')
                ->where('account_id', $context->accountId())
                ->where('establishment_id', $context->establishment()?->id)
                ->where('id', $request->route('stockId'))
                ->update(['quantity' => $request->integer('quantity')]);
            abort_if($updated === 0, 404);

            return [
                'establishment_id' => $context->establishment()?->id,
                'actor_id' => $context->actorId($user),
                'membership_id' => $context->membershipId($user),
            ];
        };

        $products = function (Request $request, OrganizationContext $context) {
            abort_unless($request->user()->can('account.companies.view', $context->node()), 403);

            return [
                'mode' => $context->mode(),
                'names' => DB::table('fixture_products')
                    ->where('account_id', $context->accountId())
                    ->where('company_id', $context->company()?->id)
                    ->orderBy('name')
                    ->pluck('name'),
            ];
        };

        Route::middleware(['web', 'auth', 'verified', 'account'])->prefix('contas/{conta}')->name('account.fixture.')->group(
            function () use ($stock, $record, $update, $products) {
                Route::prefix('estabelecimento/{estabelecimento}')
                    ->where(['estabelecimento' => OrganizationCode::ROUTE_PATTERN])
                    ->group(function () use ($stock, $record, $update) {
                        Route::get('estoque', $stock)
                            ->middleware('organization:establishment,consolidated')
                            ->name('stock.index');
                        Route::get('estoque/{stockId}', $record)
                            ->middleware('organization:establishment')
                            ->name('stock.show');
                        Route::patch('estoque/{stockId}', $update)
                            ->middleware('organization:establishment,consolidated')
                            ->name('stock.update');
                        Route::get('historico', fn () => ['ok' => true])
                            ->middleware('organization:establishment,history')
                            ->name('history.index');
                        Route::post('historico', fn () => ['ok' => true])
                            ->middleware('organization:establishment,history')
                            ->name('history.store');
                    });

                Route::prefix('empresa/{empresa}')
                    ->where(['empresa' => OrganizationCode::ROUTE_PATTERN])
                    ->middleware('organization:company')
                    ->group(function () use ($products) {
                        Route::get('produtos', $products)->name('products.index');
                    });
            },
        );

        app('router')->getRoutes()->refreshNameLookups();
    }

    private function applyNodes(Builder $query, AuthorizedNodes $nodes): void
    {
        if ($nodes->tenant) {
            return;
        }

        $query->where(fn ($covered) => $covered
            ->whereIn('company_id', $nodes->companies)
            ->orWhereIn('establishment_id', $nodes->establishments));
    }

    /**
     * Conta do exemplo: Gartan Roupas (2 unidades), Gartan Express (3) e Gartan 3 (1).
     *
     * @return array{account: Client, companies: array<string, Company>, units: array<string, Establishment>}
     */
    private function gartan(): array
    {
        $account = $this->account('Grupo Gartan');
        $roupas = $this->company($account, 'Gartan Roupas', '11222333');
        $express = $this->company($account, 'Gartan Express', '99888777');
        $third = $this->company($account, 'Gartan 3', '55666777');
        $units = [
            'roupas-guanambi' => $roupas->primaryEstablishment()->sole(),
            'roupas-janauba' => $this->unit($roupas, 'Janaúba'),
            'express-guanambi' => $express->primaryEstablishment()->sole(),
            'express-janauba' => $this->unit($express, 'Janaúba'),
            'express-jequie' => $this->unit($express, 'Jequié'),
            'gartan3' => $third->primaryEstablishment()->sole(),
        ];

        DB::table('fixture_products')->insert([
            ['account_id' => $account->id, 'company_id' => $roupas->id, 'name' => 'Camisa'],
            ['account_id' => $account->id, 'company_id' => $express->id, 'name' => 'Entrega'],
        ]);

        foreach ($units as $unit) {
            $this->stock($unit);
        }

        return [
            'account' => $account,
            'companies' => ['roupas' => $roupas, 'express' => $express, 'third' => $third],
            'units' => $units,
        ];
    }

    /** Gerente na Gartan Roupas inteira e operadora só em Janaúba da Gartan Express. */
    private function ana(array $gartan): User
    {
        $ana = User::factory()->forAccount($gartan['account']->id)->create(['name' => 'Ana']);
        $this->replaceAssignments($ana, [
            [$this->role($gartan['account'], 'Gerente', [
                'account.establishments.view',
                'account.establishments.update',
                'account.companies.view',
            ]), AccessNode::company($gartan['account']->id, $gartan['companies']['roupas']->id)],
            [$this->role($gartan['account'], 'Operador', [
                'account.establishments.view',
            ]), $this->node($gartan['units']['express-janauba'])],
        ]);

        return $ana;
    }

    /** @param list<array{0: Role, 1: AccessNode}> $assignments */
    private function replaceAssignments(User $user, array $assignments): void
    {
        $membership = $this->membership($user);
        $membership->assignments()->delete();

        foreach ($assignments as [$role, $node]) {
            RoleAssignment::grant($membership, $role, $node, null)->saveOrFail();
        }
    }

    private function stock(Establishment $unit): int
    {
        return DB::table('fixture_stock')->insertGetId([
            'account_id' => $unit->account_id,
            'company_id' => $unit->company_id,
            'establishment_id' => $unit->id,
            'product' => 'Produto '.$unit->name,
            'quantity' => 10,
        ]);
    }

    private function node(Establishment $unit): AccessNode
    {
        return AccessNode::establishment($unit->account_id, $unit->company_id, $unit->id);
    }

    private function account(string $name): Client
    {
        return Client::create(['name' => $name, 'active' => true]);
    }

    private function company(Client $account, string $name, string $root): Company
    {
        $data = SaveCompanyData::fromInput([
            'name' => $name,
            'person_type' => 'company',
            'document' => $root,
        ], $account->id, null);

        return app(CreateCompanyAction::class)->execute($account->id, $data);
    }

    private function unit(Company $company, string $name): Establishment
    {
        $data = SaveEstablishmentData::fromInput([
            'name' => $name,
            'classification' => 'unregistered',
            'address' => ['city' => 'Guanambi', 'state' => 'BA'],
        ], $company, null);

        return app(SaveEstablishmentAction::class)->execute($company, null, $data);
    }

    private function role(Client $account, string $name, array $slugs): Role
    {
        $role = Role::firstOrCreate(['name' => $name, 'account_id' => $account->id]);
        $role->permissions()->syncWithoutDetaching(Permission::whereIn('slug', $slugs)->pluck('id'));

        return $role;
    }
}
