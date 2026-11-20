<?php

namespace Tests\Feature;

use App\Domains\Acessos\Application\Queries\PermissionRegistry;
use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $account = Client::create(['name' => 'Conta', 'active' => true]);

        return User::factory()->asAccountAdministrator($account->id)->create();
    }

    private function operator(User $owner, array $slugs): User
    {
        $role = Role::create(['account_id' => $owner->account_id, 'name' => 'Operador']);
        $role->permissions()->sync(Permission::whereIn('slug', $slugs)->pluck('id'));

        return User::factory()->forAccount($owner->account_id, $role)->create();
    }

    public function test_catalog_is_discovered_idempotently_and_preserves_role_assignments(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $custom = Permission::create([
            'slug' => 'account.custom-legacy.view',
            'name' => 'Registro legado',
            'module' => 'legacy',
            'scope' => 'account',
            'account_id' => $owner->account_id,
            'is_system' => false,
        ]);
        $catalogId = Permission::where('slug', 'account.people.list')->value('id');
        $role = Role::create(['account_id' => $owner->account_id, 'name' => 'Atendente']);
        $role->permissions()->sync([$catalogId]);
        $this->artisan('permissions:sync')->assertSuccessful();
        $count = Permission::count();
        $this->artisan('permissions:sync')->assertSuccessful();
        $this->assertSame($count, Permission::count());
        $this->assertSame([$catalogId], $role->permissions()->pluck('permissions.id')->all());
        foreach (app(PermissionRegistry::class)->discover() as $item) {
            $this->assertDatabaseHas('permissions', ['slug' => $item['slug'], 'is_system' => true]);
        }
        $this->assertDatabaseHas('permissions', ['slug' => 'account.users.assign-role']);
        $this->assertDatabaseHas('permissions', ['slug' => 'platform.accounts.create']);
        $this->assertDatabaseMissing('permissions', ['slug' => 'account.users.index']);
        $orphan = Permission::create([
            'slug' => 'account.legacy.removed',
            'scope' => 'account',
            'module' => 'legacy',
            'name' => 'Antiga',
            'is_system' => true,
        ]);
        $this->artisan('permissions:sync', ['--prune' => true])->assertSuccessful();
        $this->assertDatabaseMissing('permissions', ['id' => $orphan->id]);
        $this->assertDatabaseHas('permissions', ['id' => $custom->id]);
    }

    public function test_list_view_create_and_update_are_independent(): void
    {
        $owner = $this->owner();
        $user = $this->operator($owner, ['account.people.list']);
        $this->actingAs($user);
        $this->getJson('/conta/pessoas')->assertOk()->assertJsonPath('auth.permissions.0', 'account.people.list');
        $this->getJson('/conta/pessoas/'.$this->membership($owner)->person_id.'/visualizar')->assertForbidden();
        $this->getJson('/conta/pessoas/'.$this->membership($owner)->person_id.'/editar')->assertForbidden();
        $this->postJson('/conta/pessoas', ['name' => 'Bloqueado'])->assertForbidden();
        $this->getJson('/conta/cargos')->assertForbidden();
        $this->getJson('/saas/contas')->assertForbidden();
        $this->membership($user)->assignments()->sole()->role()->firstOrFail()->permissions()->detach();
        $this->getJson('/conta/pessoas')->assertForbidden();
    }

    public function test_role_and_permission_writes_are_scoped_and_cannot_escalate(): void
    {
        $owner = $this->owner();
        $other = $this->owner();
        $user = $this->operator($owner, ['account.roles.create', 'account.roles.update', 'account.roles.view']);
        $this->actingAs($user);
        $power = Permission::where('slug', 'account.users.assign-role')->sole();
        $this->postJson('/conta/cargos', ['name' => 'Escalada', 'permission_ids' => [$power->id]])->assertForbidden();
        $this->assertDatabaseMissing('roles', ['name' => 'Escalada']);
        $this->patchJson('/conta/cargos/'.$this->membership($other)->assignments()->sole()->role_id, ['name' => 'Externo'])->assertNotFound();
        $this->getJson('/conta/cargos/'.$this->membership($other)->assignments()->sole()->role_id.'/visualizar')->assertNotFound();
        $this->patchJson('/conta/permissoes/'.$power->id, [
            'name' => 'Mudou',
            'module' => 'users',
            'action' => 'assign-role',
        ])->assertNotFound();
        $this
            ->actingAs($owner)
            ->postJson(
                '/conta/cargos',
                [
                    'name' => 'SaaS',
                    'permission_ids' => [Permission::where('slug', 'platform.accounts.create')->value('id')],
                ],
            )
            ->assertUnprocessable();
    }

    public function test_creating_a_user_requires_specific_permissions_and_a_role_from_the_same_account(): void
    {
        Notification::fake();
        $owner = $this->owner();
        $other = $this->owner();
        $user = $this->operator($owner, ['account.people.create']);
        $this->actingAs($user);
        $payload = [
            'name' => 'Pessoa com acesso',
            'email' => 'pessoa@example.test',
            'mode' => 'invite',
            'role_id' => $this->membership($owner)->assignments()->sole()->role_id,
            'tag_ids' => [Tag::userTag($owner->account_id)->id],
        ];
        $this->postJson('/conta/pessoas', $payload)->assertForbidden();
        $this->assertDatabaseMissing('people', ['name' => 'Pessoa com acesso']);
        $this
            ->actingAs($owner)
            ->postJson('/conta/pessoas', [...$payload, 'role_id' => $this->membership($other)->assignments()->sole()->role_id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role_id');
        $this
            ->post(
                '/conta/cargos',
                [
                    'name' => 'Caixa',
                    'permission_ids' => [Permission::where('slug', 'account.people.list')->value('id')],
                ],
            )
            ->assertRedirect();
        $role = Role::where('account_id', $owner->account_id)->where('name', 'Caixa')->sole();
        $this
            ->post('/conta/pessoas', [...$payload, 'role_id' => $role->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $created = User::where('email', 'pessoa@example.test')->sole();
        $this->assertSame($role->id, $this->membership($created)->assignments()->sole()->role_id);
        $this->actingAs($created)->getJson('/conta/pessoas')->assertOk();
        $this->getJson('/conta/etiquetas')->assertForbidden();
    }

    public function test_role_change_is_audited_and_last_administrator_is_preserved(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $member = Role::where('account_id', $owner->account_id)->where('system_key', 'member')->sole();
        $membership = $this->membership($owner);
        $original = $membership->assignments()->sole();
        $grant = ['role_id' => $member->id, 'scope' => 'tenant'];
        $this->patchJson('/conta/usuarios/'.$membership->id.'/atribuicoes', [
            'grants' => [$grant],
            'revocations' => [$original->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('role_id');
        $this->assertSame($original->id, $membership->assignments()->sole()->id);

        $second = User::factory()->asAccountAdministrator($owner->account_id)->create();
        $secondMembership = $this->membership($second);
        $this->patchJson('/conta/usuarios/'.$secondMembership->id.'/atribuicoes', [
            'grants' => [$grant],
            'revocations' => [$secondMembership->assignments()->sole()->id],
        ])->assertOk();
        $this->assertDatabaseHas('audit_records', [
            'entity_type' => 'role-assignment',
            'event' => 'created',
            'actor_id' => $owner->id,
            'account_id' => $owner->account_id,
        ]);
        $this->assertSame($member->id, $secondMembership->assignments()->sole()->role_id);
    }

    public function test_read_only_inspection_cannot_change_roles_or_permissions(): void
    {
        $owner = $this->owner();
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $this->actingAs($platform);
        $this
            ->post('/saas/contas/'.$owner->account_id.'/inspecao', ['user_id' => $owner->id, 'mode' => 'read-only'])
            ->assertRedirect();
        $this->postJson('/conta/cargos', ['name' => 'Bloqueado'])->assertForbidden();
        $this->postJson('/conta/permissoes', [
            'name' => 'Bloqueado',
            'module' => 'teste',
            'action' => 'view',
        ])->assertNotFound();
    }

    public function test_create_without_list_returns_to_an_authorized_form(): void
    {
        $owner = $this->owner();
        $creator = $this->operator($owner, ['account.people.create']);
        $this
            ->actingAs($creator)
            ->post('/conta/pessoas', ['name' => 'Cadastro sem listagem'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/conta/pessoas/cadastrar');
        $this->getJson('/conta/pessoas/cadastrar')->assertOk();
        $this->getJson('/conta/pessoas')->assertForbidden();
    }

    public function test_assigning_a_role_can_read_the_current_choice_without_granting_user_detail_access(): void
    {
        $owner = $this->owner();
        $operator = $this->operator($owner, [
            'account.people.update',
            'account.people.view',
            'account.users.assign-role',
        ]);
        $this
            ->actingAs($operator)
            ->getJson('/conta/pessoas/'.$this->membership($owner)->person_id.'/editar')
            ->assertOk()
            ->assertJsonPath('person.membership.assignments.0.scope', 'tenant');
        $this->getJson('/conta/pessoas/'.$this->membership($owner)->person_id.'/visualizar')->assertOk()->assertJsonPath('user', null);
    }

    public function test_permissions_are_selected_in_roles_and_have_no_management_endpoints(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        foreach ([
            '/conta/permissoes',
            '/conta/permissoes/cadastrar',
            '/conta/permissoes/1/editar',
            '/conta/permissoes/1/visualizar',
        ] as $url) {
            $this->getJson($url)->assertNotFound();
        }
        $this->postJson('/conta/permissoes', ['name' => 'Indevida'])->assertNotFound();
        $this->patchJson('/conta/permissoes/1', ['name' => 'Indevida'])->assertNotFound();
        $legacy = Permission::create([
            'slug' => 'account.custom-old.view',
            'name' => 'Legada',
            'scope' => 'account',
            'module' => 'old',
            'is_system' => false,
            'account_id' => $owner->account_id,
        ]);
        $this
            ->getJson('/conta/cargos/cadastrar')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'account.people.create'])
            ->assertJsonMissing(['slug' => $legacy->slug]);
        $this
            ->postJson('/conta/cargos', ['name' => 'Cargo inválido', 'permission_ids' => [$legacy->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('permission_ids.0');
        $this->assertDatabaseMissing('permissions', ['module' => 'permissions', 'is_system' => true]);
    }

    public function test_retiring_permission_management_cleans_old_grants_and_preserves_other_access(): void
    {
        $owner = $this->owner();
        $obsolete = Permission::create([
            'slug' => 'account.permissions.create',
            'name' => 'Antiga',
            'scope' => 'account',
            'module' => 'permissions',
            'is_system' => true,
        ]);
        $legacy = Permission::create([
            'slug' => 'account.custom-legacy.create',
            'name' => 'Legada',
            'scope' => 'account',
            'module' => 'permissions',
            'is_system' => false,
            'account_id' => $owner->account_id,
        ]);
        $this->membership($owner)->assignments()->sole()->role()->sole()->permissions()->attach($obsolete->id);
        $migration = require database_path('migrations/2026_11_18_000003_retire_permission_management.php');
        $migration->up();
        $this->assertDatabaseMissing('permissions', ['id' => $obsolete->id]);
        $this->assertDatabaseMissing('permission_role', ['permission_id' => $obsolete->id]);
        $this->assertDatabaseHas('permissions', ['id' => $legacy->id]);
        $this->assertTrue($owner->can('account.people.list'));
        $this->assertFalse($owner->can($legacy->slug));
        $this->assertDatabaseHas('access_catalog_events', [
            'event' => 'permission-management.retired',
            'scope' => 'platform',
            'actor_id' => null,
        ]);
    }

    public function test_role_list_filters_by_search_and_origin_within_the_account(): void
    {
        $owner = $this->owner();
        Role::create(['account_id' => $owner->account_id, 'name' => 'Caixa noturno']);
        Role::create(['account_id' => $this->owner()->account_id, 'name' => 'Caixa externo']);
        $initial = Role::where('account_id', $owner->account_id)->whereNotNull('system_key')->count();
        $this->actingAs($owner);

        $this
            ->getJson('/conta/cargos?search=CAIXA')
            ->assertOk()
            ->assertJsonCount(1, 'roles.data')
            ->assertJsonPath('roles.data.0.name', 'Caixa noturno')
            ->assertJsonPath('filters.search', 'CAIXA');
        $this->getJson('/conta/cargos?origin=custom')->assertOk()->assertJsonCount(1, 'roles.data');
        $this->getJson('/conta/cargos?origin=system')->assertOk()->assertJsonCount($initial, 'roles.data');
        $this
            ->getJson('/conta/cargos?origin=outro')
            ->assertOk()
            ->assertJsonPath('filters.origin', '')
            ->assertJsonCount($initial + 1, 'roles.data');
    }
}
