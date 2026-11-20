<?php

namespace Tests\Feature;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AccountContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_gate_and_page_payload_share_permissions_and_requests_observe_revocation(): void
    {
        $account = Client::create(['name' => 'Conta A', 'active' => true]);
        $role = Role::create(['account_id' => $account->id, 'name' => 'Atendimento']);
        $role->permissions()->sync(Permission::whereIn('slug', ['self.help.list', 'account.people.list'])->pluck('id'));
        $member = User::factory()->create(['account_id' => $account->id, 'role_id' => $role->id]);

        $this->actingAs($member);
        DB::enableQueryLog();
        $response = $this->getJson('/ajuda');
        $sql = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();
        DB::flushQueryLog();

        $response->assertOk()->assertJsonCount(2, 'auth.permissions');
        self::assertContains('account.people.list', $response->json('auth.permissions'));
        self::assertCount(1, array_filter($sql, fn ($query) => str_contains($query, 'from "permission_role" as "pr"')));
        self::assertCount(1, array_filter($sql, fn ($query) => str_contains($query, 'from "role_assignments"')));
        $this->getJson('/conta/cargos')->assertForbidden();
        $this->getJson('/conta/pessoas')->assertOk();

        $role->permissions()->detach(Permission::where('slug', 'account.people.list')->value('id'));
        $this->getJson('/conta/pessoas')->assertForbidden();
        $this->getJson('/ajuda')->assertOk()->assertJsonCount(1, 'auth.permissions');

        $owner = User::factory()->create(['account_id' => $account->id, 'is_account_admin' => true]);
        $membership = AccountMembership::where('user_id', $member->id)->sole();
        $this->actingAs($owner)->patchJson('/conta/usuarios/'.$membership->id.'/atribuicoes', [
            'grants' => [['role_id' => $owner->role_id, 'scope' => 'tenant']],
        ])->assertOk();
        $this->actingAs($member->fresh())->getJson('/conta/cargos')->assertOk();

        $account->update(['active' => false]);
        $this->getJson('/ajuda')->assertForbidden();
        $account->update(['active' => true]);
        $member->forceFill(['access_enabled' => false])->saveOrFail();
        $this->actingAs($member)->getJson('/ajuda')->assertUnauthorized();
    }

    public function test_removing_a_permission_from_own_role_changes_the_redirect_in_the_same_request(): void
    {
        $account = Client::create(['name' => 'Conta A', 'active' => true]);
        $role = Role::create(['account_id' => $account->id, 'name' => 'Operador']);
        $permissions = Permission::whereIn('slug', ['account.roles.list', 'account.roles.update'])
            ->pluck('id', 'slug');
        $role->permissions()->sync($permissions->values());
        $operator = User::factory()->create(['account_id' => $account->id, 'role_id' => $role->id]);

        $this->actingAs($operator)->patchJson('/conta/cargos/'.$role->id, [
            'name' => $role->name,
            'permission_ids' => [$permissions['account.roles.update']],
        ])->assertOk()->assertJsonPath('redirect', url('/conta/cargos/'.$role->id.'/editar'));
        $this->getJson('/conta/cargos')->assertForbidden();
    }

    public function test_subjects_and_scoped_instances_remain_independent(): void
    {
        $first = Client::create(['name' => 'Conta A', 'active' => true]);
        $second = Client::create(['name' => 'Conta B', 'active' => true]);
        $owner = User::factory()->create(['account_id' => $first->id, 'is_account_admin' => true]);
        $member = User::factory()->create(['account_id' => $second->id]);
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $personal = User::factory()->create();
        $invalid = User::factory()->create(['account_id' => $second->id, 'role_id' => $owner->role_id]);
        $context = app(AccountContext::class);
        $permissions = app(EffectivePermissions::class);

        self::assertSame($first->id, $context->activeAccount($owner)?->id);
        self::assertSame($second->id, $context->activeAccount($member)?->id);
        self::assertNull($context->activeAccount($platform));
        self::assertTrue($owner->can('account.people.list'));
        self::assertFalse($owner->can('platform.accounts.list'));
        self::assertFalse($member->can('account.people.list'));
        self::assertFalse($invalid->can('account.people.list'));
        self::assertTrue($platform->can('platform.accounts.list'));
        self::assertFalse($platform->can('account.people.list'));
        self::assertTrue($personal->can('self.help.list'));
        self::assertFalse($personal->can('account.people.list'));

        $this->app->forgetScopedInstances();
        self::assertNotSame($permissions, app(EffectivePermissions::class));
        self::assertNotSame($context, app(AccountContext::class));
    }
}
