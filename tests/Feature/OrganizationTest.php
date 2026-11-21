<?php

namespace Tests\Feature;

use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Auditoria\Domain\Models\AuditRecord;
use App\Domains\Organizacao\Application\Actions\CreateCompanyAction;
use App\Domains\Organizacao\Application\Actions\SaveEstablishmentAction;
use App\Domains\Organizacao\Application\Data\SaveCompanyData;
use App\Domains\Organizacao\Application\Data\SaveEstablishmentData;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_company_provisions_its_primary_establishment_in_one_operation(): void
    {
        [$account, $owner] = $this->account();

        $this->actingAs($owner)
            ->post('/conta/empresas', $this->companyPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect('/conta/empresas');

        $company = Company::query()->sole();
        $primary = Establishment::query()->sole();

        $this->assertSame($account->id, $company->account_id);
        $this->assertSame('gartan-3', $company->code);
        $this->assertSame('11222333', $company->tax_root);
        $this->assertSame('company', $company->person_type);
        $this->assertTrue($company->active);

        $this->assertSame($company->id, $primary->company_id);
        $this->assertSame($account->id, $primary->account_id);
        $this->assertSame('gartan-3-guanambi', $primary->code);
        $this->assertSame('Unidade principal (Guanambi)', $primary->name);
        $this->assertSame('11222333000181', $primary->tax_document);
        $this->assertTrue($primary->is_primary);
        $this->assertTrue($primary->is_headquarters);
        $this->assertTrue($primary->active);
        $this->assertSame('7734510000', $primary->phone);

        // Empresa e unidade principal ficam no mesmo evento de operação, com o contexto organizacional (DA-12).
        $companyEvent = AuditRecord::where('entity_type', 'company')->sole();
        $primaryEvent = AuditRecord::where('entity_type', 'establishment')->sole();

        $this->assertSame($companyEvent->operation_id, $primaryEvent->operation_id);
        $this->assertSame($account->id, $companyEvent->account_id);
        $this->assertSame($company->id, $companyEvent->company_id);
        $this->assertNull($companyEvent->establishment_id);
        $this->assertSame($company->id, $primaryEvent->company_id);
        $this->assertSame($primary->id, $primaryEvent->establishment_id);
        $this->assertSame($owner->id, $companyEvent->actor_id);
    }

    public function test_a_failure_while_provisioning_leaves_no_company_and_no_audit(): void
    {
        [$account] = $this->account();
        $data = SaveCompanyData::fromInput($this->companyPayload(), $account->id, null);

        Event::listen('eloquent.creating: '.Establishment::class, function (): never {
            throw new \RuntimeException('Falha simulada na unidade principal.');
        });

        try {
            app(CreateCompanyAction::class)->execute($account->id, $data);
            $this->fail('A criação deveria falhar.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Falha simulada na unidade principal.', $exception->getMessage());
        }

        $this->assertDatabaseCount('companies', 0);
        $this->assertDatabaseCount('establishments', 0);
        $this->assertSame(0, AuditRecord::whereIn('entity_type', ['company', 'establishment'])->count());
    }

    public function test_a_company_with_one_establishment_is_listed_and_grows_to_several_units(): void
    {
        [, $owner] = $this->account();
        $company = $this->createCompany($owner);

        $this->actingAs($owner)->getJson('/conta/empresas')
            ->assertOk()
            ->assertJsonPath('companies.data.0.id', $company->id);

        $this->getJson('/conta/empresas?page=2')->assertOk()->assertJsonCount(0, 'companies.data');

        $this->getJson('/conta/empresas/'.$company->id.'/editar')
            ->assertOk()
            ->assertJsonPath('company.document_label', '11.222.333');

        $this->post('/conta/estabelecimentos', $this->establishmentPayload($company, [
            'name' => 'Loja Centro',
            'classification' => 'branch',
            'tax_document' => '11.222.333/0002-62',
        ]))->assertSessionHasNoErrors()->assertRedirect('/conta/estabelecimentos');

        $this->post('/conta/estabelecimentos', $this->establishmentPayload($company, [
            'name' => 'Quiosque Shopping',
            'classification' => 'unregistered',
            'tax_document' => '11.222.333/0003-43',
        ]))->assertSessionHasNoErrors();

        $kiosk = Establishment::where('name', 'Quiosque Shopping')->sole();

        $this->assertNull($kiosk->tax_document, 'Unidade sem CNPJ próprio não guarda CNPJ.');
        $this->assertFalse($kiosk->is_primary);
        $this->assertSame('gartan-3-quiosque-shopping', $kiosk->code);
        $this->assertSame(3, $company->establishments()->count());
        $this->assertSame(1, $company->establishments()->where('is_primary', true)->count());

        $this->getJson('/conta/estabelecimentos')
            ->assertOk()
            ->assertJsonCount(3, 'establishments.data')
            ->assertJsonPath('establishments.data.0.name', 'Loja Centro')
            ->assertJsonPath('establishments.data.0.classification', 'branch')
            ->assertJsonPath('establishments.data.0.tax_document_label', '11.222.333/0002-62')
            ->assertJsonPath('establishments.data.1.classification', 'unregistered')
            ->assertJsonPath('establishments.data.2.is_primary', true)
            ->assertJsonPath('establishments.data.2.city', 'Guanambi');
    }

    public function test_organization_expands_only_an_authorized_company_and_returns_to_it_after_saving(): void
    {
        [, $owner] = $this->account();
        $company = $this->createCompany($owner);
        [, $other] = $this->account('Outra conta');
        $foreign = $this->createCompany($other);
        $primary = $company->primaryEstablishment()->sole();

        $this->actingAs($owner)->getJson('/conta/organizacao')
            ->assertOk()
            ->assertJsonCount(1, 'companies.data')
            ->assertJsonPath('establishments.data.0.id', $primary->id);

        $this->getJson('/conta/organizacao?recolhida=1')
            ->assertOk()
            ->assertJsonPath('establishments', null);

        $branch = '/conta/organizacao?empresa='.$company->id;
        $this->getJson($branch)
            ->assertOk()
            ->assertJsonPath('expandedCompany', $company->id)
            ->assertJsonPath('establishments.data.0.id', $primary->id);
        $this->getJson('/conta/organizacao?empresa='.$foreign->id)->assertNotFound();

        $this->patch('/conta/empresas/'.$company->id.'?origem=organizacao', ['name' => 'Empresa editada'])
            ->assertSessionHasNoErrors()
            ->assertRedirect($branch);

        $this->patch('/conta/estabelecimentos/'.$primary->id.'?origem=organizacao', ['name' => 'Unidade editada'])
            ->assertSessionHasNoErrors()
            ->assertRedirect($branch);

        $this->patch('/conta?origem=organizacao', ['name' => 'Conta editada'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/conta/organizacao?nivel=conta');

        $member = Role::where('account_id', $company->account_id)->where('system_key', 'member')->sole();
        $reader = User::factory()->forAccount($company->account_id, $member)->create();

        $this->actingAs($reader)->getJson('/conta/organizacao')
            ->assertOk()
            ->assertJsonPath('companies', null)
            ->assertJsonPath('establishments.data.0.id', $primary->id);
        $this->getJson('/conta/organizacao?empresa='.$foreign->id)->assertNotFound();
    }

    public function test_an_account_with_several_companies_lists_counts_and_filters_in_the_database(): void
    {
        [, $owner] = $this->account();
        $first = $this->createCompany($owner);
        $second = $this->createCompany($owner, [
            'name' => 'Alfa Distribuidora Ltda',
            'trade_name' => 'Alfa',
            'document' => '99.888.777/0001-00',
        ]);
        $third = $this->createCompany($owner, [
            'person_type' => 'individual',
            'name' => 'Produtor Rural',
            'trade_name' => null,
            'document' => '529.982.247-25',
            'primary' => ['classification' => 'headquarters'],
        ]);

        $this->post('/conta/estabelecimentos', $this->establishmentPayload($second, [
            'name' => 'Filial Norte',
            'classification' => 'branch',
            'tax_document' => '99.888.777/0002-82',
        ]))->assertSessionHasNoErrors();

        $this->actingAs($owner)
            ->getJson('/conta/empresas')
            ->assertOk()
            ->assertJsonCount(3, 'companies.data')
            ->assertJsonPath('companies.data.0.name', 'Alfa Distribuidora Ltda')
            ->assertJsonPath('companies.data.0.establishments_count', 2)
            ->assertJsonPath('companies.data.1.establishments_count', 1)
            ->assertJsonPath('companies.data.2.document_label', '529.982.247-25');

        $this->getJson('/conta/empresas?search=99.888.777/0001-00')
            ->assertJsonCount(1, 'companies.data')
            ->assertJsonPath('companies.data.0.id', $second->id);

        $this->getJson('/conta/empresas?search=gartan')
            ->assertJsonCount(1, 'companies.data')
            ->assertJsonPath('companies.data.0.id', $first->id);

        $this->getJson('/conta/estabelecimentos?company='.$second->id)
            ->assertJsonCount(2, 'establishments.data')
            ->assertJsonCount(3, 'companies');

        $this->getJson('/conta/estabelecimentos?search=99.888.777/0002-82')
            ->assertJsonCount(1, 'establishments.data')
            ->assertJsonPath('establishments.data.0.name', 'Filial Norte');

        $this->patch('/conta/empresas/'.$third->id, ['active' => '0'])->assertSessionHasNoErrors();

        $this->getJson('/conta/empresas?status=inactive')
            ->assertJsonCount(1, 'companies.data')
            ->assertJsonPath('companies.data.0.id', $third->id);

        $this->getJson('/conta/empresas/'.$second->id.'/visualizar')
            ->assertOk()
            ->assertJsonPath('id', $second->id)
            ->assertJsonMissingPath('establishments');

        $thirdPrimary = $third->primaryEstablishment()->sole();

        $this->assertNull($thirdPrimary->tax_document);
        $this->assertFalse($thirdPrimary->is_headquarters, 'Empresa com CPF não tem matriz.');
    }

    public function test_two_primary_establishments_are_impossible_and_promotion_is_atomic(): void
    {
        [, $owner] = $this->account();
        $company = $this->createCompany($owner);
        $primary = $company->primaryEstablishment()->sole();

        $this->actingAs($owner)
            ->post('/conta/estabelecimentos', $this->establishmentPayload($company, [
                'name' => 'Loja Centro',
                'is_primary' => '1',
            ]))
            ->assertSessionHasNoErrors();

        $branch = Establishment::where('name', 'Loja Centro')->sole();

        $this->assertFalse($branch->is_primary, 'O formulário não marca a principal: isso é a promoção.');

        try {
            DB::table('establishments')->where('id', $branch->id)->update(['is_primary' => true]);
            $this->fail('O índice parcial deveria impedir duas principais.');
        } catch (UniqueConstraintViolationException) {
            $this->assertFalse($branch->refresh()->is_primary);
        }

        AuditRecord::query()->delete();

        $this->post('/conta/estabelecimentos/'.$branch->id.'/principal')
            ->assertSessionHasNoErrors()
            ->assertRedirect('/conta/estabelecimentos');

        $this->assertTrue($branch->refresh()->is_primary);
        $this->assertFalse($primary->refresh()->is_primary);

        $events = AuditRecord::where('entity_type', 'establishment')->orderBy('id')->get();

        $this->assertCount(2, $events);
        $this->assertSame($events[0]->operation_id, $events[1]->operation_id);
        $this->assertSame([$primary->id, $branch->id], $events->pluck('establishment_id')->all());
        $this->assertSame([$company->id, $company->id], $events->pluck('company_id')->all());
        $this->assertSame(['before' => true, 'after' => false], array_intersect_key(
            $events[0]->changes['is_primary'],
            ['before' => 0, 'after' => 0],
        ));

        $this->artisan('organization:check')->assertSuccessful();
    }

    public function test_the_primary_and_the_last_active_establishment_cannot_be_deactivated(): void
    {
        [, $owner] = $this->account();
        $company = $this->createCompany($owner);
        $primary = $company->primaryEstablishment()->sole();

        // A principal é a última unidade ativa: inativá-la deixaria a empresa ativa sem unidade.
        $this->actingAs($owner)
            ->patch('/conta/estabelecimentos/'.$primary->id, ['active' => '0'])
            ->assertSessionHasErrors(['active' => 'Promova outro estabelecimento a principal antes de inativar este.']);

        $this->assertTrue($primary->refresh()->active);

        $this->post('/conta/estabelecimentos', $this->establishmentPayload($company, ['name' => 'Loja Centro']));
        $branch = Establishment::where('name', 'Loja Centro')->sole();

        $this->patch('/conta/estabelecimentos/'.$primary->id, ['active' => '0'])->assertSessionHasErrors('active');

        $this->post('/conta/estabelecimentos/'.$branch->id.'/principal')->assertSessionHasNoErrors();
        $this->patch('/conta/estabelecimentos/'.$primary->id, ['active' => '0'])->assertSessionHasNoErrors();

        $this->assertFalse($primary->refresh()->active);
        $this->assertTrue($branch->refresh()->is_primary);

        // Unidade inativa não é promovida.
        $this->post('/conta/estabelecimentos/'.$primary->id.'/principal')->assertSessionHasErrors('establishment');
        $this->assertTrue($branch->refresh()->is_primary);

        // A restrição do banco também protege a principal ativa.
        $this->expectException(QueryException::class);
        DB::table('establishments')->where('id', $branch->id)->update(['active' => false]);
    }

    public function test_an_inactive_company_keeps_its_units_and_rejects_new_ones_and_promotions(): void
    {
        [, $owner] = $this->account();
        $company = $this->createCompany($owner);

        $this->actingAs($owner)->post('/conta/estabelecimentos', $this->establishmentPayload($company, [
            'name' => 'Loja Centro',
        ]));

        $branch = Establishment::where('name', 'Loja Centro')->sole();

        $this->patch('/conta/estabelecimentos/'.$branch->id, ['active' => '0'])->assertSessionHasNoErrors();
        $this->patch('/conta/empresas/'.$company->id, ['active' => '0'])->assertSessionHasNoErrors();

        $this->assertFalse($company->refresh()->active);
        $this->assertTrue($company->primaryEstablishment()->sole()->active);

        $this->post('/conta/estabelecimentos', $this->establishmentPayload($company, ['name' => 'Nova']))
            ->assertSessionHasErrors(['company_id' => 'A empresa está inativa e não recebe estabelecimentos novos.']);

        $this->patch('/conta/estabelecimentos/'.$branch->id, ['active' => '1'])->assertSessionHasErrors('active');
        $this->post('/conta/estabelecimentos/'.$branch->id.'/principal')->assertSessionHasErrors('establishment');

        $this->patch('/conta/empresas/'.$company->id, ['active' => '1'])->assertSessionHasNoErrors();
        $this->patch('/conta/estabelecimentos/'.$branch->id, ['active' => '1'])->assertSessionHasNoErrors();

        $this->assertSame(2, $company->establishments()->where('active', true)->count());
        $this->artisan('organization:check')->assertSuccessful();
    }

    public function test_headquarters_and_branches_follow_the_explicit_classification_and_the_company_root(): void
    {
        [, $owner] = $this->account();
        $company = $this->createCompany($owner, ['primary' => ['classification' => 'branch']]);
        $primary = $company->primaryEstablishment()->sole();

        // A ordem 0001 não define matriz: a classificação é explícita.
        $this->assertFalse($primary->is_headquarters);
        $this->assertSame('branch', $primary->legalClassification());

        $this->actingAs($owner)->post('/conta/estabelecimentos', $this->establishmentPayload($company, [
            'name' => 'Sede',
            'classification' => 'headquarters',
            'tax_document' => '11.222.333/0002-62',
        ]))->assertSessionHasNoErrors();

        $this->post('/conta/estabelecimentos', $this->establishmentPayload($company, [
            'name' => 'Segunda sede',
            'classification' => 'headquarters',
            'tax_document' => '11.222.333/0003-43',
        ]))->assertSessionHasErrors('company_id');

        $this->post('/conta/estabelecimentos', $this->establishmentPayload($company, [
            'name' => 'Outra pessoa jurídica',
            'classification' => 'branch',
            'tax_document' => '99.888.777/0002-82',
        ]))->assertSessionHasErrors(['tax_document' => 'O CNPJ deve ter a mesma raiz da empresa (11.222.333).']);

        $this->post('/conta/estabelecimentos', $this->establishmentPayload($company, [
            'name' => 'Filial sem CNPJ',
            'classification' => 'branch',
            'tax_document' => '',
        ]))->assertSessionHasErrors(['tax_document' => 'Informe o CNPJ da matriz ou da filial.']);

        $this->post('/conta/estabelecimentos', $this->establishmentPayload($company, [
            'name' => 'CNPJ repetido',
            'classification' => 'branch',
            'tax_document' => '11.222.333/0001-81',
        ]))->assertSessionHasErrors(['tax_document' => 'Já existe um estabelecimento com este CNPJ nesta conta.']);

        $this->assertSame(1, $company->establishments()->where('is_headquarters', true)->count());

        // Uma matriz fora da raiz impede trocar o documento da empresa.
        $this->patch('/conta/empresas/'.$company->id, ['document' => '99.888.777/0001-00'])
            ->assertSessionHasErrors('document');

        $this->assertSame('11222333', $company->refresh()->tax_root);
    }

    public function test_saving_an_establishment_rechecks_its_root_against_the_locked_company(): void
    {
        [, $owner] = $this->account();
        $company = $this->createCompany($owner);
        $data = SaveEstablishmentData::fromInput([
            'name' => 'Filial',
            'classification' => 'branch',
            'tax_document' => '11.222.333/0002-62',
        ], $company, null);

        DB::table('companies')->where('id', $company->id)->update(['tax_root' => '11222334']);

        try {
            app(SaveEstablishmentAction::class)->execute($company, null, $data);
            $this->fail('O CNPJ foi aceito com a raiz anterior da empresa.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('tax_document', $exception->errors());
        }

        $this->assertSame(1, $company->establishments()->count());
    }

    public function test_tax_documents_are_validated_including_alphanumeric_cnpj(): void
    {
        [, $owner] = $this->account();
        $this->actingAs($owner);

        foreach ([null, 'unregistered'] as $classification) {
            $this->post('/conta/empresas', $this->companyPayload([
                'primary' => ['classification' => $classification],
            ]))->assertSessionHasErrors('primary.classification');
        }

        $this->post('/conta/empresas', $this->companyPayload(['document' => '11.222.333/0001-82']))
            ->assertSessionHasErrors(['document' => 'Informe um CNPJ completo ou a raiz com 8 caracteres.']);

        $this->post('/conta/empresas', $this->companyPayload([
            'person_type' => 'individual',
            'document' => '529.982.247-24',
        ]))->assertSessionHasErrors(['document' => 'Informe um CPF válido.']);

        $this->post('/conta/empresas', $this->companyPayload(['document' => '']))
            ->assertSessionHasErrors(['document' => 'Informe o CNPJ ou o CPF da empresa.']);

        $this->post('/conta/empresas', $this->companyPayload(['code' => 'todos']))->assertSessionHasErrors('code');
        $this->post('/conta/empresas', $this->companyPayload(['main_cnae' => '47.1']))
            ->assertSessionHasErrors('main_cnae');
        $this->assertDatabaseCount('companies', 0);

        $company = $this->createCompany($owner, [
            'name' => 'Empresa Alfanumérica',
            'trade_name' => 'Nova Era',
            'document' => '12.abc.345/01de-35',
            'main_cnae' => '4711-3/02',
            'tax_regime' => 'simples-nacional',
        ]);

        $this->assertSame('12ABC345', $company->tax_root);
        $this->assertSame('4711302', $company->main_cnae);
        $this->assertSame('12ABC34501DE35', $company->primaryEstablishment()->sole()->tax_document);

        $this->post('/conta/estabelecimentos', $this->establishmentPayload($company, [
            'name' => 'Filial alfanumérica',
            'classification' => 'branch',
            'tax_document' => '12.ABC.345/02DE-06',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(
            '12ABC34502DE06',
            Establishment::where('name', 'Filial alfanumérica')->value('tax_document'),
        );

        // A raiz isolada também identifica a empresa; a unidade principal fica sem CNPJ próprio.
        $rootOnly = $this->createCompany($owner, ['document' => '99.888.777', 'trade_name' => 'Raiz']);

        $this->assertSame('99888777', $rootOnly->tax_root);
        $this->assertSame('unregistered', $rootOnly->primaryEstablishment()->sole()->legalClassification());
    }

    public function test_fiscal_uniqueness_is_per_account_and_never_reveals_other_accounts(): void
    {
        [, $owner] = $this->account();
        [, $otherOwner] = $this->account('Outra conta');
        $this->createCompany($owner);

        $this->actingAs($owner)
            ->post('/conta/empresas', $this->companyPayload([
                'trade_name' => 'Gartan filial',
                'document' => '11.222.333/0002-62',
            ]))
            ->assertSessionHasErrors(['document' => 'Já existe uma empresa com esta raiz de CNPJ nesta conta.']);

        // A mesma pessoa jurídica em outra conta é aceita, sem revelar que já existe.
        $this->actingAs($otherOwner)
            ->post('/conta/empresas', $this->companyPayload())
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Company::where('tax_root', '11222333')->count());
        $this->assertSame(2, Establishment::where('tax_document', '11222333000181')->count());
    }

    public function test_identifiers_from_another_account_answer_not_found(): void
    {
        [, $owner] = $this->account();
        [, $otherOwner] = $this->account('Outra conta');
        $own = $this->createCompany($owner);
        $foreign = $this->createCompany($otherOwner, ['trade_name' => 'Concorrente']);
        $foreignUnit = $foreign->primaryEstablishment()->sole();

        $this->actingAs($owner);

        $this->getJson('/conta/empresas/'.$foreign->id.'/editar')->assertNotFound();
        $this->getJson('/conta/empresas/'.$foreign->id.'/visualizar')->assertNotFound();
        $this->patch('/conta/empresas/'.$foreign->id, ['name' => 'Invadida'])->assertNotFound();
        $this->getJson('/conta/estabelecimentos/'.$foreignUnit->id.'/editar')->assertNotFound();
        $this->getJson('/conta/estabelecimentos/'.$foreignUnit->id.'/visualizar')->assertNotFound();
        $this->getJson('/conta/estabelecimentos?company='.$foreign->id)->assertNotFound();
        $this->getJson('/conta/estabelecimentos/cadastrar?empresa='.$foreign->id)->assertNotFound();
        $this->patch('/conta/estabelecimentos/'.$foreignUnit->id, ['name' => 'Invadida'])->assertNotFound();
        $this->post('/conta/estabelecimentos/'.$foreignUnit->id.'/principal')->assertNotFound();

        // Vincular unidade nova a empresa de outra conta.
        $this->post('/conta/estabelecimentos', $this->establishmentPayload($foreign, ['name' => 'Intrusa']))
            ->assertNotFound();

        // A empresa proprietária não muda na edição, mesmo com company_id manipulado.
        $ownUnit = $own->primaryEstablishment()->sole();

        $this->patch('/conta/estabelecimentos/'.$ownUnit->id, ['company_id' => $foreign->id, 'name' => 'Renomeada'])
            ->assertSessionHasNoErrors();

        $this->assertSame($own->id, $ownUnit->refresh()->company_id);
        $this->assertSame('Renomeada', $ownUnit->name);
        $this->assertSame('Concorrente', $foreign->refresh()->trade_name);
        $this->assertSame(1, $foreign->establishments()->count());

        $this->getJson('/conta/estabelecimentos')
            ->assertJsonCount(1, 'establishments.data')
            ->assertJsonCount(1, 'companies');
        $this->getJson('/conta/empresas?search=Concorrente&status=active')->assertJsonCount(0, 'companies.data');
    }

    public function test_company_codes_are_suggested_unique_and_immutable(): void
    {
        [, $owner] = $this->account();
        $first = $this->createCompany($owner, ['trade_name' => 'Gartan']);
        $second = $this->createCompany($owner, ['trade_name' => 'Gartan', 'document' => '99.888.777/0001-00']);
        $custom = $this->createCompany($owner, [
            'code' => 'grupo-x',
            'document' => '529.982.247-25',
            'person_type' => 'individual',
        ]);

        $this->assertSame('gartan', $first->code);
        $this->assertSame('gartan-2', $second->code);
        $this->assertSame('grupo-x', $custom->code);

        $this->actingAs($owner)->post('/conta/empresas', $this->companyPayload([
            'code' => 'gartan',
            'document' => '11.144.477/0001-67',
        ]))->assertSessionHasErrors(['code' => 'Já existe uma empresa com este código nesta conta.']);

        $this->patch('/conta/empresas/'.$first->id, ['code' => 'outro', 'name' => 'Gartan Ltda'])
            ->assertSessionHasNoErrors();

        $this->assertSame('gartan', $first->refresh()->code);
        $this->assertSame('Gartan Ltda', $first->name);
    }

    public function test_each_action_has_its_own_permission(): void
    {
        [$account, $owner] = $this->account();
        $company = $this->createCompany($owner);
        $primary = $company->primaryEstablishment()->sole();

        $this->post('/conta/estabelecimentos', $this->establishmentPayload($company, ['name' => 'Loja Centro']));
        $branch = Establishment::where('name', 'Loja Centro')->sole();

        $editor = $this->member($account, ['account.companies.update', 'account.establishments.update']);

        $this->actingAs($editor);
        $this->getJson('/conta/empresas')->assertForbidden();
        $this->getJson('/conta/estabelecimentos')->assertForbidden();
        $this->getJson('/conta/empresas/'.$company->id.'/visualizar')->assertForbidden();
        $this->post('/conta/empresas', $this->companyPayload(['document' => '99.888.777/0001-00']))->assertForbidden();
        $this->post('/conta/estabelecimentos', $this->establishmentPayload($company, ['name' => 'X']))
            ->assertForbidden();
        $this->post('/conta/estabelecimentos/'.$branch->id.'/principal')->assertForbidden();

        // Editar não concede ativar nem inativar.
        $this->patch('/conta/empresas/'.$company->id, ['active' => '0'])->assertForbidden();
        $this->patch('/conta/estabelecimentos/'.$branch->id, ['active' => '0'])->assertForbidden();
        $this->patch('/conta/estabelecimentos/'.$branch->id, ['name' => 'Loja Central'])->assertSessionHasNoErrors();

        $this->assertTrue($company->refresh()->active);
        $this->assertTrue($branch->refresh()->active);
        $this->assertSame('Loja Central', $branch->name);
        $this->assertTrue($primary->refresh()->is_primary);

        $promoter = $this->member($account, ['account.establishments.promote']);
        $this->actingAs($promoter)
            ->post('/conta/estabelecimentos/'.$branch->id.'/principal')
            ->assertSessionHasNoErrors();
        $this->assertTrue($branch->refresh()->is_primary);

        $deactivator = $this->member($account, ['account.establishments.update', 'account.establishments.deactivate']);
        $this->actingAs($deactivator)
            ->patch('/conta/estabelecimentos/'.$primary->id, ['active' => '0'])
            ->assertSessionHasNoErrors();

        $this->assertFalse($primary->refresh()->active);
    }

    public function test_initial_roles_and_the_renamed_account_data_permission(): void
    {
        [$account, $owner] = $this->account();
        $member = User::factory()->forAccount($account->id)->create();

        $this->assertNull(Permission::where('slug', 'account.establishment.update')->first());
        $this->assertNull(Permission::where('slug', 'like', 'account.points.%')->first());

        $ownerPermissions = $owner->permissionSlugs();

        foreach ([
            'account.account-data.update',
            'account.companies.list',
            'account.companies.activate',
            'account.establishments.promote',
            'account.establishments.deactivate',
        ] as $slug) {
            $this->assertContains($slug, $ownerPermissions);
        }

        $this->assertNotContains('platform.accounts.list', $ownerPermissions);
        $this->assertContains('account.establishments.list', $member->permissionSlugs());
        $this->assertNotContains('account.establishments.update', $member->permissionSlugs());

        $this->actingAs($owner)->getJson('/conta/editar')->assertOk()->assertJsonPath('client.id', $account->id);
        $this->actingAs($member)->getJson('/conta/editar')->assertForbidden();

        // Um cargo personalizado não recebe permissão que o operador não tem.
        $limited = $this->member($account, ['account.roles.create', 'account.roles.list']);
        $deactivate = Permission::where('slug', 'account.companies.deactivate')->value('id');

        $this->actingAs($limited)
            ->post('/conta/cargos', ['name' => 'Escalada', 'permission_ids' => [$deactivate]])
            ->assertForbidden();

        $this->assertNull(Role::where('name', 'Escalada')->first());
    }

    public function test_the_platform_provisions_the_contractor_as_first_company_only_when_declared(): void
    {
        $this->actingAs(User::factory()->create(['is_saas_admin' => true]));

        $account = [
            'name' => 'Gartan Comércio Ltda',
            'person_type' => 'company',
            'document' => '11.222.333/0001-81',
            'email' => 'contato@gartan.test',
            'details' => [
                'trade_name' => 'Gartan 3',
                'phone' => '(77) 3451-0000',
                'address' => ['city' => 'Guanambi', 'state' => 'BA'],
            ],
        ];

        $this->post('/saas/contas', [
            ...$account,
            'organization_setup' => 'automatic',
            'administrator' => $this->administrator('owner@gartan.test'),
        ])->assertSessionHasNoErrors();

        $declared = Company::query()->sole();
        $primary = $declared->primaryEstablishment()->sole();

        $this->assertSame('11222333', $declared->tax_root);
        $this->assertSame('Gartan 3', $declared->trade_name);
        $this->assertSame('11222333000181', $primary->tax_document);
        $this->assertTrue($primary->is_headquarters);
        $this->assertSame('Unidade principal (Guanambi)', $primary->name);
        $this->assertSame('contato@gartan.test', $primary->email);

        $this->post('/saas/contas', [
            ...$account,
            'document' => '99.888.777/0001-00',
            'administrator' => $this->administrator('owner@outra.test'),
        ])->assertSessionHasNoErrors();

        $undeclared = Company::where('id', '!=', $declared->id)->sole();

        $this->assertNull($undeclared->tax_root);
        $this->assertNull($undeclared->person_type);
        $this->assertNull($undeclared->primaryEstablishment()->sole()->tax_document);
        $this->assertSame('Unidade principal', $undeclared->primaryEstablishment()->sole()->name);

        $this->artisan('organization:check')
            ->expectsOutputToContain('Pendência: Empresa #'.$undeclared->id)
            ->assertSuccessful();
    }

    public function test_manual_account_provisioning_validates_and_audits_the_company_and_primary_unit(): void
    {
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $payload = [
            'name' => 'Contratante',
            'person_type' => 'company',
            'document' => '99.888.777/0001-00',
            'details' => ['phone' => '(77) 3451-0000'],
            'organization_setup' => 'manual',
            'administrator' => $this->administrator('owner@manual.test'),
            'first_company' => [
                'name' => 'Empresa informada',
                'person_type' => 'company',
                'document' => 'inválido',
                'primary' => [
                    'name' => 'Loja Guanambi',
                    'classification' => 'branch',
                    'phone' => '(77) 3451-0001',
                    'address' => ['city' => 'Guanambi', 'state' => 'BA'],
                ],
            ],
        ];

        $this->actingAs($platform)->postJson('/saas/contas', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('first_company.document');

        $this->assertDatabaseCount('clients', 0);
        $this->assertDatabaseCount('companies', 0);
        $this->assertDatabaseCount('establishments', 0);

        $payload['first_company']['document'] = '11.222.333/0001-81';
        $this->postJson('/saas/contas', $payload)->assertOk();

        $account = Client::query()->sole();
        $company = Company::query()->sole();
        $unit = $company->primaryEstablishment()->sole();
        $this->assertSame('99888777000100', $account->document);
        $this->assertSame($account->id, $company->account_id);
        $this->assertSame('Empresa informada', $company->name);
        $this->assertSame('11222333', $company->tax_root);
        $this->assertSame('11222333000181', $unit->tax_document);
        $this->assertSame('Loja Guanambi', $unit->name);
        $this->assertSame('7734510001', $unit->phone);
        $this->assertSame('Guanambi', $unit->address['city']);
        $this->assertFalse($unit->is_headquarters);

        $event = AuditRecord::where('entity_type', 'establishment')->sole();
        $this->assertSame($platform->id, $event->actor_id);
        $this->assertSame($account->id, $event->account_id);
        $this->assertSame($company->id, $event->company_id);
        $this->assertSame($unit->id, $event->establishment_id);
        $this->assertSame(AuditRecord::where('entity_type', 'company')->sole()->operation_id, $event->operation_id);
    }

    public function test_inspection_preserves_the_saas_author_on_organizational_events(): void
    {
        [$account, $owner] = $this->account();
        $company = $this->createCompany($owner);
        $platform = User::factory()->create(['is_saas_admin' => true]);

        $this->actingAs($platform)
            ->post(route('saas.accounts.inspection.start', $account), ['user_id' => $owner->id, 'mode' => 'edit'])
            ->assertRedirect();

        $this->post('/conta/estabelecimentos', $this->establishmentPayload($company, ['name' => 'Inspecionada']))
            ->assertSessionHasNoErrors();

        $unit = Establishment::where('name', 'Inspecionada')->sole();
        $event = AuditRecord::where('entity_type', 'establishment')->where('entity_id', $unit->id)->sole();

        $this->assertSame($platform->id, $event->actor_id);
        $this->assertSame($owner->id, $event->represented_user_id);
        $this->assertSame($account->id, $event->account_id);
        $this->assertSame($company->id, $event->company_id);
        $this->assertSame($unit->id, $event->establishment_id);

        $this->post(route('account.inspection.end'));
        $this->post(route('saas.accounts.inspection.start', $account), [
            'user_id' => $owner->id,
            'mode' => 'read-only',
        ]);

        $this->post('/conta/estabelecimentos', $this->establishmentPayload($company, ['name' => 'Bloqueada']))
            ->assertForbidden();
        $this->post('/conta/estabelecimentos/'.$unit->id.'/principal')->assertForbidden();
        $this->assertNull(Establishment::where('name', 'Bloqueada')->first());
    }

    public function test_home_summarizes_the_organization_for_the_permissions_of_the_user(): void
    {
        [$account, $owner] = $this->account();
        $this->createCompany($owner);
        $member = User::factory()->forAccount($account->id)->create();

        $this->actingAs($owner)
            ->getJson('/conta')
            ->assertOk()
            ->assertJsonPath('organization.companies', 1)
            ->assertJsonPath('organization.establishments', 1)
            ->assertJsonCount(0, 'organization.incomplete');

        $this->actingAs($member)
            ->getJson('/conta')
            ->assertOk()
            ->assertJsonPath('organization.companies', null)
            ->assertJsonPath('organization.establishments', 1);
    }

    public function test_organization_check_reports_violations_without_changing_data(): void
    {
        [$account, $owner] = $this->account();
        $company = $this->createCompany($owner);
        $primary = $company->primaryEstablishment()->sole();

        $this->artisan('organization:check')->assertSuccessful();

        Client::create(['name' => 'Conta sem empresa', 'active' => true]);
        DB::table('establishments')->where('id', $primary->id)->update(['is_primary' => false]);
        DB::table('establishments')->insert([
            'account_id' => $account->id,
            'company_id' => $company->id,
            'code' => 'fora-da-raiz',
            'name' => 'Fora da raiz',
            'tax_document' => '99888777000282',
            'active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('organization:check')
            ->expectsOutputToContain('sem empresa')
            ->expectsOutputToContain('Empresa #'.$company->id.': 0 estabelecimentos principais')
            ->expectsOutputToContain('fora da raiz da empresa')
            ->assertFailed();

        $this->assertFalse($primary->refresh()->is_primary, 'O comando não corrige dados.');
    }

    public function test_listings_keep_a_constant_number_of_queries(): void
    {
        [, $owner] = $this->account();
        $company = $this->createCompany($owner);

        $this->actingAs($owner);
        $this->createCompany($owner, ['trade_name' => 'Segunda', 'document' => '99.888.777/0001-00']);

        $small = [
            '/conta/empresas' => $this->queriesFor('/conta/empresas'),
            '/conta/estabelecimentos' => $this->queriesFor('/conta/estabelecimentos'),
        ];

        foreach (range(3, 12) as $index) {
            $document = str_pad((string) (10 + $index), 8, '0', STR_PAD_LEFT);
            $this->createCompany($owner, ['trade_name' => 'Empresa '.$index, 'document' => $document]);
            $this->post('/conta/estabelecimentos', $this->establishmentPayload($company, ['name' => 'Loja '.$index]));
        }

        $this->assertSame(12, Company::count());
        $this->assertSame(22, Establishment::count());

        foreach ($small as $path => $queries) {
            $this->assertSame($queries, $this->queriesFor($path), $path.' não pode crescer com as linhas.');
        }

        // Permissões continuam resolvidas uma vez por requisição (E03).
        DB::enableQueryLog();
        $this->getJson('/conta/estabelecimentos')->assertOk();
        $log = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();
        DB::flushQueryLog();

        $this->assertCount(1, array_filter($log, fn (string $sql) => str_contains($sql, 'from "permission_role" as "pr"')));
        $this->assertCount(1, array_filter($log, fn (string $sql) => str_contains($sql, 'from "clients"')));
    }

    private function queriesFor(string $path): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson($path)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        return $count;
    }

    /** @return array{Client, User} */
    private function account(string $name = 'Conta Gartan'): array
    {
        $account = Client::create(['name' => $name, 'active' => true]);
        $owner = User::factory()->asAccountAdministrator($account->id)->create();

        return [$account, $owner];
    }

    /** @param list<string> $slugs */
    private function member(Client $account, array $slugs): User
    {
        $role = Role::create(['account_id' => $account->id, 'name' => 'Cargo '.implode(' ', $slugs)]);
        $role->permissions()->sync(Permission::whereIn('slug', $slugs)->pluck('id'));

        return User::factory()->forAccount($account->id, $role)->create();
    }

    /** @param array<string,mixed> $overrides */
    private function createCompany(User $owner, array $overrides = []): Company
    {
        $before = Company::where('account_id', $owner->account_id)->max('id') ?? 0;

        $this->actingAs($owner)
            ->post('/conta/empresas', $this->companyPayload($overrides))
            ->assertSessionHasNoErrors();

        return Company::where('account_id', $owner->account_id)->where('id', '>', $before)->sole();
    }

    /**
     * @param  array<string,mixed>  $overrides
     * @return array<string,mixed>
     */
    private function companyPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'person_type' => 'company',
            'name' => 'Gartan Comércio Ltda',
            'trade_name' => 'Gartan 3',
            'document' => '11.222.333/0001-81',
            'primary' => [
                'classification' => 'headquarters',
                'address' => ['city' => 'Guanambi', 'state' => 'BA'],
                'phone' => '(77) 3451-0000',
            ],
        ], $overrides);
    }

    /**
     * @param  array<string,mixed>  $overrides
     * @return array<string,mixed>
     */
    private function establishmentPayload(Company $company, array $overrides = []): array
    {
        return [
            'company_id' => $company->id,
            'name' => 'Loja',
            'classification' => 'unregistered',
            'address' => ['city' => 'Caetité', 'state' => 'BA'],
            ...$overrides,
        ];
    }

    /** @return array<string,string> */
    private function administrator(string $email): array
    {
        return [
            'name' => 'Administrador',
            'email' => $email,
            'mode' => 'manual',
            'password' => 'SenhaForte123!',
            'password_confirmation' => 'SenhaForte123!',
        ];
    }
}
