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
        $operator = User::factory()->create(['account_id' => $account->id]);
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
        $operator = User::factory()->create(['account_id' => $account->id]);
        $member = User::factory()->create(['account_id' => $account->id]);
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
        $owner = User::factory()->create(['account_id' => $account->id, 'is_account_admin' => true]);
        $role = $this->role($account, 'Auditoria', ['account.audit.list']);
        $member = User::factory()->create(['account_id' => $account->id, 'role_id' => $role->id]);
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
        $user = User::factory()->create(['account_id' => $a->id]);
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
        $user = User::factory()->create(['account_id' => $account->id]);
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

    public function test_migration_recreates_tenant_assignments_without_changing_the_effective_permission_set(): void
    {
        $account = Client::create(['name' => 'Conta de teste', 'active' => true]);
        $owner = User::factory()->create(['account_id' => $account->id, 'is_account_admin' => true]);
        $member = User::factory()->create(['account_id' => $account->id]);
        $expected = [$owner->permissionSlugs(), $member->permissionSlugs()];

        $migration = require database_path('migrations/2026_11_20_000007_create_contextual_access.php');
        $migration->down();
        $migration->up();
        app(EffectivePermissions::class)->forget();

        $this->assertSame($expected, [$owner->permissionSlugs(), $member->permissionSlugs()]);
        $this->assertSame('tenant', $this->membership($owner)->assignments()->sole()->scope);
        $this->assertSame('tenant', $this->membership($member)->assignments()->sole()->scope);
    }

    public function test_reactivation_does_not_reactivate_a_disabled_identity(): void
    {
        $account = Client::create(['name' => 'Conta de teste', 'active' => true]);
        $owner = User::factory()->create(['account_id' => $account->id, 'is_account_admin' => true]);
        $member = User::factory()->create(['account_id' => $account->id, 'access_enabled' => false]);
        $membership = $this->membership($member);
        $membership->status = 'suspended';
        $membership->saveOrFail();

        $this->actingAs($owner)->patchJson('/conta/usuarios/'.$membership->id.'/reativar')->assertOk();
        $this->assertSame('active', $membership->fresh()->status);
        $this->assertFalse($member->fresh()->access_enabled);
        $this->assertFalse($member->fresh()->can('account.establishments.list'));

        $this->patchJson('/conta/usuarios/'.$this->membership($owner)->id.'/suspender')->assertUnprocessable();
    }

    public function test_tenant_user_creation_returns_the_same_response_for_an_existing_global_identity(): void
    {
        $account = Client::create(['name' => 'Conta de teste', 'active' => true]);
        $other = Client::create(['name' => 'Outra conta', 'active' => true]);
        $owner = User::factory()->create(['account_id' => $account->id, 'is_account_admin' => true]);
        $existing = User::factory()->create(['account_id' => $other->id]);
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
        $owner = User::factory()->create(['account_id' => $account->id, 'is_account_admin' => true]);
        $member = User::factory()->create(['account_id' => $account->id]);
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
        $operator = User::factory()->create(['account_id' => $account->id]);
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
        $this->assertNull($user->role_id);
        $this->assertFalse($user->can('account.establishments.list', AccessNode::company($account->id, $a->id)));
    }

    public function test_suspending_one_membership_does_not_affect_another_membership_of_the_same_identity(): void
    {
        $first = Client::create(['name' => 'Conta A', 'active' => true]);
        $second = Client::create(['name' => 'Conta B', 'active' => true]);
        $owner = User::factory()->create(['account_id' => $first->id, 'is_account_admin' => true]);
        $member = User::factory()->create(['account_id' => $first->id]);
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

    private function membership(User $user): AccountMembership
    {
        return AccountMembership::where('user_id', $user->id)->where('account_id', $user->account_id)->sole();
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
