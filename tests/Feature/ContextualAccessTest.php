<?php

namespace Tests\Feature;

use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Acessos\Domain\Models\RoleAssignment;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Auditoria\Domain\Models\AuditRecord;
use App\Domains\Organizacao\Application\Actions\CreateCompanyAction;
use App\Domains\Organizacao\Application\Data\SaveCompanyData;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ContextualAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_inheritance_is_tenant_safe_and_audit_filters_are_applied_before_pagination(): void
    {
        $account = Client::create(['name' => 'Conta A', 'active' => true]);
        $a = $this->company($account, 'Empresa A', '11222333');
        $b = $this->company($account, 'Empresa B', '99888777');
        $first = $a->primaryEstablishment()->sole();
        $second = $b->primaryEstablishment()->sole();
        $operator = User::factory()->forAccount($account->id)->create();
        $membership = $this->membership($operator);
        $role = $this->role($account, 'Auditor local', [
            'account.audit.list', 'account.audit.view', 'account.establishments.list',
        ]);

        $membership->assignments()->delete();
        RoleAssignment::grant($membership, $role, AccessNode::company($account->id, $a->id), null)->saveOrFail();

        AuditLog::record(
            'establishment',
            $first->id,
            $account->id,
            'updated',
            'Evento permitido',
            ['name' => ['label' => 'Nome', 'before' => 'A', 'after' => 'B']],
            companyId: $a->id,
            establishmentId: $first->id,
        );
        AuditLog::record(
            'establishment',
            $second->id,
            $account->id,
            'updated',
            'Evento fora do escopo',
            ['name' => ['label' => 'Nome', 'before' => 'A', 'after' => 'B']],
            companyId: $b->id,
            establishmentId: $second->id,
        );
        AuditLog::record('account', $account->id, $account->id, 'created', 'Evento do tenant');

        $matching = AuditRecord::where('entity_name', 'Evento permitido')->sole();

        $this->actingAs($operator)->getJson('/conta/estabelecimentos')
            ->assertOk()
            ->assertJsonPath('establishments.total', 1)
            ->assertJsonPath('establishments.data.0.id', $first->id);
        $this->getJson('/conta/auditoria?search=Evento')
            ->assertOk()
            ->assertJsonPath('records.total', 1)
            ->assertJsonPath('records.data.0.name', 'Evento permitido');
        $this->getJson('/conta/auditoria/'.$matching->id.'/visualizar')->assertOk()->assertJsonPath('technical', null);

        $this->assertFalse($operator->can('account.audit.view', AccessNode::tenant($account->id)));
        $this->assertFalse($operator->can('account.audit.view', AccessNode::company($account->id, $b->id)));
    }

    public function test_local_operator_cannot_delegate_or_revoke_outside_its_scope(): void
    {
        $account = Client::create(['name' => 'Conta de teste', 'active' => true]);
        $a = $this->company($account, 'Empresa A', '11222333');
        $b = $this->company($account, 'Empresa B', '99888777');
        $aNode = AccessNode::company($account->id, $a->id);
        $bNode = AccessNode::company($account->id, $b->id);
        $operator = User::factory()->forAccount($account->id)->create();
        $member = User::factory()->forAccount($account->id)->create();
        $target = $this->membership($member);
        $manager = $this->role($account, 'Gestor', ['account.users.assign-role', 'account.users.list', 'account.audit.list']);
        $auditor = $this->role($account, 'Auditor', ['account.audit.list']);
        $superior = $this->role($account, 'Auditor completo', ['account.audit.list', 'account.audit.technical']);
        RoleAssignment::grant($this->membership($operator), $manager, $aNode, null)->saveOrFail();
        $outside = RoleAssignment::grant($target, $auditor, $bNode, null);
        $outside->saveOrFail();
        $url = '/conta/usuarios/'.$target->id.'/atribuicoes';

        $this->actingAs($operator)->patchJson($url, ['revocations' => [$outside->id]])->assertForbidden();
        $this->patchJson($url, [
            'grants' => [['role_id' => $superior->id, 'scope' => 'company', 'company_id' => $a->id]],
        ])->assertForbidden();
        $this->patchJson($url, [
            'grants' => [['role_id' => $auditor->id, 'scope' => 'company', 'company_id' => $a->id]],
        ])->assertOk();
        $this->getJson('/conta/usuarios')
            ->assertOk()
            ->assertJsonFragment(['location' => 'Empresa A'])
            ->assertJsonFragment(['id' => $target->id, 'can_view' => false])
            ->assertJsonMissing(['location' => 'Empresa B']);

        $inside = $target->assignments()->where('company_id', $a->id)->sole();
        $this->patchJson($url, ['revocations' => [$inside->id]])->assertOk();

        $this->assertDatabaseHas('role_assignments', ['id' => $outside->id]);
        $this->assertDatabaseMissing('role_assignments', ['id' => $inside->id]);
        $this->assertDatabaseHas('audit_records', [
            'entity_type' => 'role-assignment',
            'entity_id' => $inside->id,
            'company_id' => $a->id,
            'event' => 'deleted',
            'actor_id' => $operator->id,
        ]);
    }

    public function test_role_availability_requires_confirmation_and_removes_uncovered_assignments(): void
    {
        $account = Client::create(['name' => 'Conta de teste', 'active' => true]);
        $company = $this->company($account, 'Empresa', '11222333');
        $owner = User::factory()->asAccountAdministrator($account->id)->create();
        $role = $this->role($account, 'Auditoria', ['account.audit.list']);
        $member = User::factory()->forAccount($account->id, $role)->create();
        $assignment = $this->membership($member)->assignments()->sole();
        $url = '/conta/cargos/'.$role->id;
        $input = [
            'name' => $role->name,
            'permission_ids' => $role->permissionIds(),
            'availability' => 'restricted',
            'available_companies' => [$company->id],
        ];

        $this->actingAs($owner)->patchJson($url, $input)->assertUnprocessable()->assertJsonValidationErrors('availability');
        $this->getJson($url.'/impacto-abrangencia?availability=restricted&available_companies[]='.$company->id)
            ->assertOk()->assertJsonPath('assignments.0.id', $assignment->id);
        $this->patchJson($url, [...$input, 'confirmed_revocations' => [$assignment->id]])->assertOk();
        $this->assertDatabaseMissing('role_assignments', ['id' => $assignment->id]);
        $this->assertDatabaseHas('role_availability', ['role_id' => $role->id, 'company_id' => $company->id]);
        $this->assertFalse($member->fresh()->can('account.audit.list'));
    }

    public function test_database_rejects_a_cross_tenant_assignment(): void
    {
        $a = Client::create(['name' => 'Conta A', 'active' => true]);
        $b = Client::create(['name' => 'Conta B', 'active' => true]);
        $user = User::factory()->forAccount($a->id)->create();
        $role = $this->role($b, 'Outro cargo', []);

        $this->expectException(QueryException::class);

        DB::table('role_assignments')->insert([
            'account_id' => $a->id,
            'membership_id' => $this->membership($user)->id,
            'role_id' => $role->id,
            'scope' => 'tenant',
        ]);
    }

    public function test_resolution_uses_two_queries_at_each_assignment_volume_and_caches_within_request(): void
    {
        $account = Client::create(['name' => 'Conta de teste', 'active' => true]);
        $user = User::factory()->forAccount($account->id)->create();
        $membership = $this->membership($user);
        $membership->assignments()->delete();
        $node = AccessNode::tenant($account->id);

        for ($index = 1; $index <= 30; $index++) {
            $role = $this->role($account, 'Cargo '.$index, ['account.audit.list']);
            RoleAssignment::grant($membership, $role, $node, null)->saveOrFail();

            if (! in_array($index, [1, 5, 30], true)) {
                continue;
            }

            $permissions = app(EffectivePermissions::class);
            $permissions->forget();
            DB::enableQueryLog();
            DB::flushQueryLog();

            self::assertTrue($permissions->allows($user, 'account.audit.list', $node));
            self::assertTrue($permissions->allows($user, 'account.audit.list', $node));
            self::assertCount(2, DB::getQueryLog());

            DB::disableQueryLog();
        }
    }

    public function test_membership_is_the_only_source_for_an_identitys_person_and_assignments(): void
    {
        $account = Client::create(['name' => 'Conta de teste', 'active' => true]);
        $owner = User::factory()->asAccountAdministrator($account->id)->create();
        $standalone = User::factory()->create();

        $this->assertFalse(Schema::hasColumn('users', 'role_id'));
        $this->assertFalse(Schema::hasColumn('users', 'person_id'));
        $this->assertFalse(Schema::hasColumn('users', 'is_account_admin'));
        $this->assertTrue(Schema::hasColumn('users', 'account_id'));
        $this->assertSame('administrator', $this->membership($owner)->assignments()->sole()->role()->sole()->system_key);
        $this->assertSame($owner->name, $this->membership($owner)->person()->sole()->name);
        $this->assertFalse($standalone->memberships()->exists());
        $this->assertDatabaseMissing('people', ['email' => $standalone->email]);
    }

    public function test_assignment_requests_without_changes_never_resolve_the_target(): void
    {
        $account = Client::create(['name' => 'Conta de teste', 'active' => true]);
        $owner = User::factory()->asAccountAdministrator($account->id)->create();
        $member = User::factory()->forAccount($account->id)->create();
        $existing = $this->membership($member);

        foreach ([[$member, 403], [$owner, 422]] as [$actor, $status]) {
            $this->actingAs($actor);

            foreach ([$existing->id, $existing->id + 100] as $membershipId) {
                DB::enableQueryLog();
                DB::flushQueryLog();
                $this->patchJson('/conta/usuarios/'.$membershipId.'/atribuicoes', [
                    'grants' => [],
                    'revocations' => [],
                ])->assertStatus($status);

                $sql = array_column(DB::getQueryLog(), 'query');
                DB::disableQueryLog();
                $this->assertSame([], array_values(array_filter($sql, fn (string $query): bool => str_contains($query, 'from "account_memberships" where "account_id"')
                    || preg_match('/^(insert|update|delete).*"(users|role_assignments|audit_records)"/i', $query) === 1)));
            }
        }

        $this->assertSame(1, $existing->assignments()->count());
    }

    public function test_reactivation_does_not_reactivate_a_disabled_identity(): void
    {
        $account = Client::create(['name' => 'Conta de teste', 'active' => true]);
        $owner = User::factory()->asAccountAdministrator($account->id)->create();
        $member = User::factory()->forAccount($account->id)->create(['access_enabled' => false]);
        $membership = $this->membership($member);
        $membership->status = 'suspended';
        $membership->saveOrFail();

        $this->actingAs($owner)->patchJson('/conta/usuarios/'.$membership->id.'/reativar')->assertOk();
        $this->assertSame('active', $membership->fresh()->status);
        $this->assertFalse($member->fresh()->access_enabled);
        $this->assertFalse($member->fresh()->can('account.establishments.list'));

        $this->patchJson('/conta/usuarios/'.$this->membership($owner)->id.'/suspender')->assertUnprocessable();
    }

    public function test_account_user_can_reuse_an_existing_person_without_changing_the_central_record(): void
    {
        $account = Client::create(['name' => 'Conta de teste', 'active' => true]);
        $other = Client::create(['name' => 'Outra conta', 'active' => true]);
        $owner = User::factory()->asAccountAdministrator($account->id)->create();
        $person = Person::create([
            'account_id' => $account->id,
            'name' => 'Pessoa candidata',
            'email' => 'contato@example.test',
            'phone' => '71988887777',
        ]);
        $foreign = Person::create(['account_id' => $other->id, 'name' => 'Pessoa candidata externa']);
        $role = $this->role($account, 'Operador', ['account.establishments.list']);

        $this->actingAs($owner)->getJson('/conta/usuarios/pessoas?q=candidata')
            ->assertOk()->assertJsonCount(1, 'people')->assertJsonPath('people.0.id', $person->id);

        $data = [
            'person_registration' => 'existing',
            'name' => 'Nome forjado',
            'email' => 'acesso@example.test',
            'mode' => 'invite',
            'role_id' => $role->id,
        ];
        $this->postJson('/conta/usuarios', $data)->assertJsonValidationErrors('person_id');
        $this->postJson('/conta/usuarios', [...$data, 'person_id' => $foreign->id])->assertNotFound();

        $people = Person::count();
        $this->postJson('/conta/usuarios', [...$data, 'person_id' => $person->id])->assertOk();
        $identity = User::where('email', 'acesso@example.test')->sole();
        $this->assertSame($person->id, $this->membership($identity)->person_id);
        $this->assertSame('Pessoa candidata', $identity->name);
        $this->assertSame('contato@example.test', $person->fresh()->email);
        $this->assertSame('71988887777', $person->fresh()->phone);
        $this->assertSame($people, Person::count());
        $this->getJson('/conta/usuarios/pessoas?q=candidata')->assertJsonCount(0, 'people');
    }

    public function test_tenant_user_creation_returns_the_same_response_for_an_existing_global_identity(): void
    {
        $account = Client::create(['name' => 'Conta de teste', 'active' => true]);
        $other = Client::create(['name' => 'Outra conta', 'active' => true]);
        $owner = User::factory()->asAccountAdministrator($account->id)->create();
        $existing = User::factory()->forAccount($other->id)->create();
        $role = $this->role($account, 'Operador', ['account.establishments.list']);
        $data = ['name' => 'Acesso novo', 'mode' => 'invite', 'role_id' => $role->id];

        $this->actingAs($owner);
        $refused = $this->postJson('/conta/usuarios', [...$data, 'email' => $existing->email])->assertOk();
        $this->assertDatabaseMissing('account_memberships', ['user_id' => $existing->id, 'account_id' => $account->id]);
        $accepted = $this->postJson('/conta/usuarios', [...$data, 'email' => 'available@example.test'])->assertOk();

        $this->assertSame($refused->json(), $accepted->json());
        $this->assertSame(2, AccountMembership::where('account_id', $account->id)->count());
    }

    public function test_admin_can_only_request_safe_password_recovery_and_resend_pending_invitations(): void
    {
        $account = Client::create(['name' => 'Conta de teste', 'active' => true]);
        $owner = User::factory()->asAccountAdministrator($account->id)->create();
        $member = User::factory()->forAccount($account->id)->create();
        $membership = $this->membership($member);
        $password = $member->password;
        Notification::fake();

        $this->actingAs($owner)->postJson('/conta/usuarios/'.$membership->id.'/convite')->assertUnprocessable();
        $this->postJson('/conta/usuarios/'.$membership->id.'/recuperacao', [
            'email' => 'different@example.test',
            'password' => 'ignored',
        ])->assertOk();
        Notification::assertSentTo($member, ResetPassword::class);
        $this->assertSame($password, $member->fresh()->password);
        $this->assertDatabaseHas('audit_records', [
            'entity_type' => 'account-membership',
            'entity_id' => $membership->id,
            'actor_id' => $owner->id,
            'event' => 'password-reset-requested',
        ]);
    }

    public function test_local_creation_provisions_the_first_assignment_only_in_the_authorized_unit(): void
    {
        $account = Client::create(['name' => 'Conta de teste', 'active' => true]);
        $a = $this->company($account, 'Empresa A', '11222333');
        $b = $this->company($account, 'Empresa B', '99888777');
        $unit = $a->primaryEstablishment()->sole();
        $other = $b->primaryEstablishment()->sole();
        $operator = User::factory()->forAccount($account->id)->create();
        $manager = $this->role($account, 'Gestor de unidade', [
            'account.users.create',
            'account.users.assign-role',
            'account.establishments.list',
        ]);
        $role = $this->role($account, 'Operador', ['account.establishments.list']);
        RoleAssignment::grant(
            $this->membership($operator),
            $manager,
            AccessNode::establishment($account->id, $a->id, $unit->id),
            null,
        )->saveOrFail();

        $payload = [
            'name' => 'Usuário local',
            'email' => 'available@example.test',
            'mode' => 'invite',
            'role_id' => $role->id,
            'scope' => 'establishment',
            'company_id' => $a->id,
            'establishment_id' => $unit->id,
        ];
        $this->actingAs($operator)->postJson('/conta/usuarios', [
            ...$payload,
            'company_id' => $b->id,
            'establishment_id' => $other->id,
        ])->assertForbidden();
        $this->postJson('/conta/usuarios', $payload)->assertOk();

        $user = User::where('email', $payload['email'])->sole();
        $assignment = $this->membership($user)->assignments()->sole();
        $this->assertSame('establishment', $assignment->scope);
        $this->assertSame($unit->id, $assignment->establishment_id);
        $this->assertFalse($user->can('account.establishments.list', AccessNode::company($account->id, $a->id)));
    }

    public function test_suspending_one_membership_does_not_affect_another_membership_of_the_same_identity(): void
    {
        $first = Client::create(['name' => 'Conta A', 'active' => true]);
        $second = Client::create(['name' => 'Conta B', 'active' => true]);
        $owner = User::factory()->asAccountAdministrator($first->id)->create();
        $member = User::factory()->forAccount($first->id)->create();
        $person = Person::create(['account_id' => $second->id, 'name' => 'Pessoa local']);
        $secondMembership = AccountMembership::register($member, $second->id, $person->id);
        $secondMembership->saveOrFail();
        $role = $this->role($second, 'Auditor', ['account.audit.list']);
        RoleAssignment::grant($secondMembership, $role, AccessNode::tenant($second->id), null)->saveOrFail();

        $this->actingAs($owner)->patchJson('/conta/usuarios/'.$this->membership($member)->id.'/suspender')->assertOk();

        $member->refresh();
        $this->assertTrue($member->access_enabled);
        $this->assertFalse($member->can('account.establishments.list'));
        $this->assertSame('active', $secondMembership->fresh()->status);
        $this->assertSame(1, $secondMembership->assignments()->count());
    }

    public function test_authorization_matrix_for_realistic_organizational_profiles(): void
    {
        $account = Client::create(['name' => 'Conta da matriz', 'active' => true]);
        $foreign = Client::create(['name' => 'Conta separada', 'active' => true]);
        $a = $this->company($account, 'Empresa A', '11222333');
        $b = $this->company($account, 'Empresa B', '99888777');
        $a1 = $a->primaryEstablishment()->sole();
        $b1 = $b->primaryEstablishment()->sole();
        $outside = $this->company($foreign, 'Empresa externa', '11222333')->primaryEstablishment()->sole();
        $owner = User::factory()->asAccountAdministrator($account->id)->create();
        $unitReader = ['account.establishments.list', 'account.establishments.view'];
        $auditReader = ['account.audit.list', 'account.audit.view'];

        $companyAdmin = $this->role($account, 'Administrador de empresa', [
            ...$unitReader, ...$auditReader, 'account.audit.technical', 'account.establishments.update',
            'account.establishments.create', 'account.users.assign-role',
        ]);
        $unitAdmin = $this->role($account, 'Administrador de unidade', [
            ...$unitReader, ...$auditReader, 'account.establishments.update', 'account.users.assign-role',
        ]);
        $manager = $this->role($account, 'Gerente', [...$unitReader, ...$auditReader]);
        $editor = $this->role($account, 'Operador com edição', [...$unitReader, 'account.establishments.update']);
        $reader = $this->role($account, 'Operador com leitura', $unitReader);
        $companyA = AccessNode::company($account->id, $a->id);
        $unitA = AccessNode::establishment($account->id, $a->id, $a1->id);
        $unitB = AccessNode::establishment($account->id, $b->id, $b1->id);

        $profiles = [
            'integral' => [$owner, 200, [$a1->id, $b1->id], true, true, true, true, true],
        ];

        foreach ([
            'empresa' => [[$companyAdmin, $companyA], 200, [$a1->id], true, false, true, true, true],
            'unidade' => [[$unitAdmin, $unitA], 200, [$a1->id], true, false, false, true, true],
            'gerente' => [[$manager, $unitA], 200, [$a1->id], false, false, false, false, true],
            'duas unidades' => [[$editor, $unitA], 200, [$a1->id, $b1->id], true, false, false, false, false],
            'sem atribuição' => [null, 403, [], false, false, false, false, false],
            'suspenso' => [[$manager, $unitA], 403, [], false, false, false, false, false],
        ] as $label => $expectations) {
            $assignment = array_shift($expectations);
            $operator = User::factory()->forAccount($account->id)->create();
            $membership = $this->membership($operator);
            $membership->assignments()->delete();

            if ($assignment !== null) {
                RoleAssignment::grant($membership, $assignment[0], $assignment[1], null)->saveOrFail();
            }

            if ($label === 'duas unidades') {
                RoleAssignment::grant($membership, $reader, $unitB, null)->saveOrFail();
            }

            if ($label === 'suspenso') {
                $membership->status = 'suspended';
                $membership->saveOrFail();
            }

            $profiles[$label] = [$operator, ...$expectations];
        }

        $platform = User::factory()->create(['is_saas_admin' => true]);
        $target = User::factory()->forAccount($account->id)->create();
        $recipient = $this->membership($target);
        $directory = '/conta/estabelecimentos';
        $audit = '/conta/auditoria';
        $assignments = '/conta/usuarios/'.$recipient->id.'/atribuicoes';

        AuditLog::record('account', $account->id, $account->id, 'created', 'Verificação de escopo conta');
        AuditLog::record('person', $recipient->person_id, $account->id, 'created', 'Verificação de escopo pessoa compartilhada');
        AuditLog::record('company', $a->id, $account->id, 'created', 'Verificação de escopo empresa', companyId: $a->id);
        AuditLog::record(
            'establishment', $a1->id, $account->id, 'created', 'Verificação de escopo unidade',
            companyId: $a->id, establishmentId: $a1->id,
        );
        AuditLog::record(
            'establishment', $b1->id, $account->id, 'created', 'Verificação de escopo outra unidade',
            companyId: $b->id, establishmentId: $b1->id,
        );

        foreach ($profiles as $label => [$actor, $status, $visible, $editA, $editB, $create, $delegate, $canAudit]) {
            $this->actingAs($actor);
            $this->getJson($directory)->assertStatus($status);
            $this->getJson($directory.'/'.$outside->id.'/visualizar')->assertStatus($label === 'suspenso' ? 403 : 404);

            if ($status === 200) {
                $response = $this->getJson($directory.'?company='.$b->id);
                $response->assertStatus(in_array($b1->id, $visible, true) ? 200 : 404);
                $response = $this->getJson($directory);
                $actual = array_column($response->json('establishments.data'), 'id');
                $this->assertEqualsCanonicalizing($visible, $actual, $label);
                $this->getJson($directory.'/'.$a1->id.'/visualizar')
                    ->assertStatus(in_array($a1->id, $visible, true) ? 200 : 404);

                $this->patchJson($directory.'/'.$a1->id, ['name' => 'Unidade A '.$label])
                    ->assertStatus($editA ? 200 : 404);
                $this->patchJson($directory.'/'.$b1->id, ['name' => 'Unidade B '.$label])
                    ->assertStatus($editB ? 200 : 404);
            }

            $this->getJson($directory.'/cadastrar')->assertStatus($create ? 200 : 403);
            $this->getJson($audit.'?search=Verifica')->assertStatus($canAudit ? 200 : 403);

            if ($canAudit) {
                $total = match ($label) {
                    'integral' => 5,
                    'empresa' => 2,
                    default => 1,
                };
                $this->getJson($audit.'?search=Verifica&per_page=1')->assertJsonPath('records.total', $total);
                $this->getJson($audit.'?entity=person&search=Verifica')
                    ->assertJsonPath('records.total', $label === 'integral' ? 1 : 0);
                $shared = AuditRecord::where('entity_name', 'Verificação de escopo pessoa compartilhada')->sole();
                $this->getJson($audit.'/'.$shared->id.'/visualizar')->assertStatus($label === 'integral' ? 200 : 404);

                $local = AuditRecord::where('entity_name', 'Verificação de escopo unidade')->sole();
                $details = $this->getJson($audit.'/'.$local->id.'/visualizar')->assertOk();
                $expectedNode = in_array($label, ['integral', 'empresa'], true) ? $a1->id : null;

                if ($expectedNode === null) {
                    $details->assertJsonPath('technical', null);
                } else {
                    $details->assertJsonPath('technical.establishment_id', $expectedNode);
                }

                if ($label !== 'integral') {
                    $this->assertNotContains('person', array_column($this->getJson($audit)->json('options.entities'), 'value'));
                }
            }

            if ($status === 200) {
                $this->patchJson($assignments, [
                    'grants' => [[
                        'role_id' => $reader->id, 'scope' => 'establishment', 'company_id' => $a->id,
                        'establishment_id' => $a1->id,
                    ]],
                ])->assertStatus($delegate ? 200 : 403);

                if ($delegate) {
                    $grant = $recipient->assignments()->where('role_id', $reader->id)->sole();
                    $event = AuditRecord::where('entity_type', 'role-assignment')->where('entity_id', $grant->id)->sole();
                    $this->getJson($audit.'/'.$event->id.'/visualizar')->assertOk();
                    $this->assertSame($a1->id, $event->establishment_id);
                    $this->patchJson($assignments, ['revocations' => [$grant->id]])->assertOk();
                }
            }
        }

        $operator = $profiles['empresa'][0];
        $newUnit = ['name' => 'Unidade nova', 'classification' => 'unregistered', 'company_id' => $a->id];
        $this->actingAs($operator)->postJson($directory, $newUnit)->assertOk();
        $this->getJson($directory)->assertJsonPath('establishments.total', 2);
        $this->postJson($directory, [...$newUnit, 'company_id' => $b->id])->assertNotFound();

        $this->actingAs($platform)->getJson($audit)->assertForbidden();
        $this->post(route('saas.accounts.inspection.start', $account), [
            'user_id' => $profiles['unidade'][0]->id, 'mode' => 'read-only',
        ])->assertRedirect();
        $this->getJson($directory)->assertOk()->assertJsonPath('establishments.total', 1);
        $this->getJson($directory.'/'.$b1->id.'/visualizar')->assertNotFound();
        $this->patchJson($directory.'/'.$a1->id, ['name' => 'Impedido na inspeção'])->assertForbidden();
        $this->getJson($audit.'?search=Verifica')->assertOk()->assertJsonPath('records.total', 1);
        $this->getJson(route('two-factor.secret-key'))->assertForbidden();
        $this->postJson(route('passkey.store'), [])->assertForbidden();
        $this->post(route('account.inspection.end'))->assertRedirect();
        $this->assertAuthenticatedAs($platform);
    }

    public function test_scope_changes_take_effect_on_the_next_request_and_prevent_lossy_rollback(): void
    {
        $account = Client::create(['name' => 'Conta de teste', 'active' => true]);
        $company = $this->company($account, 'Empresa', '11222333');
        $other = $this->company($account, 'Outra empresa', '99888777');
        $owner = User::factory()->asAccountAdministrator($account->id)->create();
        $member = User::factory()->forAccount($account->id)->create();
        $membership = $this->membership($member);
        $membership->assignments()->delete();
        $role = $this->role($account, 'Leitura restrita', ['account.establishments.list']);
        $role->availability = 'restricted';
        $role->saveOrFail();
        $role->syncAvailabilityNodes([['company_id' => $company->id, 'establishment_id' => null]]);
        $list = '/conta/estabelecimentos';
        $url = '/conta/usuarios/'.$membership->id.'/atribuicoes';
        $grant = ['role_id' => $role->id, 'scope' => 'company', 'company_id' => $company->id];

        $this->actingAs($member)->getJson($list)->assertForbidden();
        $this->actingAs($owner)->patchJson($url, [
            'grants' => [[...$grant, 'company_id' => $other->id]],
        ])->assertForbidden();
        $this->patchJson($url, ['grants' => [$grant]])->assertOk();
        $this->actingAs($member)->getJson($list)->assertOk()->assertJsonPath('establishments.total', 1);

        $id = $membership->assignments()->sole()->id;
        $migration = require database_path('migrations/2026_11_20_000007_create_contextual_access.php');

        try {
            $migration->down();
            $this->fail('A reversão deveria preservar as atribuições contextuais.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('perderia dados contextuais', $exception->getMessage());
        }

        $this->assertDatabaseHas('role_assignments', ['id' => $id]);
        $this->actingAs($owner)->patchJson($url, ['revocations' => [$id]])->assertOk();
        $this->actingAs($member)->getJson($list)->assertForbidden();
        $this->assertSame('active', $membership->fresh()->status);
        $this->assertTrue($member->fresh()->access_enabled);
    }

    /** @param list<string> $slugs */
    private function role(Client $account, string $name, array $slugs): Role
    {
        $role = Role::create(['name' => $name, 'account_id' => $account->id]);
        $role->permissions()->attach(Permission::whereIn('slug', $slugs)->pluck('id'));

        return $role;
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
}
