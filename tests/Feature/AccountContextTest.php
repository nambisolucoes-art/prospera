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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

final class AccountContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_gate_and_page_payload_share_permissions_and_requests_observe_revocation(): void
    {
        $account = Client::create(['name' => 'Conta A', 'active' => true]);
        $role = Role::create(['account_id' => $account->id, 'name' => 'Atendimento']);
        $role->permissions()->sync(Permission::whereIn('slug', ['self.help.list', 'account.tags.list'])->pluck('id'));
        $member = User::factory()->forAccount($account->id, $role)->create();

        $this->actingAs($member);
        DB::enableQueryLog();
        $response = $this->getJson('/conta/etiquetas');
        $sql = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();
        DB::flushQueryLog();

        $response->assertOk()->assertJsonCount(2, 'auth.permissions');
        self::assertContains('account.tags.list', $response->json('auth.permissions'));
        self::assertCount(1, array_filter($sql, fn ($query) => str_contains($query, 'from "permission_role" as "pr"')));
        self::assertCount(1, array_filter($sql, fn ($query) => str_contains($query, 'from "role_assignments"')));
        // Fora da conta (E09), valem só as permissões self dos vínculos ativos da identidade.
        $this->getJson('/ajuda')->assertOk()
            ->assertJsonPath('auth.permissions', ['self.help.list']);
        $this->getJson('/conta/cargos')->assertForbidden();

        $role->permissions()->detach(Permission::where('slug', 'account.tags.list')->value('id'));
        $this->getJson('/conta/etiquetas')->assertForbidden();
        $this->getJson('/ajuda')->assertOk()->assertJsonCount(1, 'auth.permissions');

        $owner = User::factory()->asAccountAdministrator($account->id)->create();
        $membership = AccountMembership::where('user_id', $member->id)->sole();
        $this->actingAs($owner)->patchJson('/conta/usuarios/'.$membership->id.'/atribuicoes', [
            'grants' => [['role_id' => $this->membership($owner)->assignments()->sole()->role_id, 'scope' => 'tenant']],
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
        $operator = User::factory()->forAccount($account->id, $role)->create();

        $this->actingAs($operator)->patchJson('/conta/cargos/'.$role->id, [
            'name' => $role->name,
            'permission_ids' => [$permissions['account.roles.update']],
        ])->assertOk()->assertJsonPath('redirect', url($this->accountPath('/conta/cargos/'.$role->id.'/editar')));
        $this->getJson('/conta/cargos')->assertForbidden();
    }

    public function test_subjects_and_scoped_instances_remain_independent(): void
    {
        $first = Client::create(['name' => 'Conta A', 'active' => true]);
        $second = Client::create(['name' => 'Conta B', 'active' => true]);
        $owner = User::factory()->asAccountAdministrator($first->id)->create();
        $member = User::factory()->forAccount($second->id)->create();
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $personal = User::factory()->create();
        $withoutMembership = User::factory()->create();
        $context = app(AccountContext::class);
        $permissions = app(EffectivePermissions::class);

        // Sem conta na requisição, nenhuma identidade tem conta ativa (E09).
        self::assertNull($context->activeAccount($owner));
        self::assertFalse($owner->can('account.tags.list'));

        $context->enter($owner, $first->code);
        $permissions->forget();
        self::assertSame($first->id, $context->activeAccount($owner)?->id);
        self::assertNull($context->activeAccount($member));
        self::assertTrue($owner->can('account.tags.list'));
        self::assertFalse($owner->can('platform.accounts.list'));
        self::assertFalse($member->can('account.tags.list'));
        self::assertFalse($withoutMembership->can('account.tags.list'));

        $context->enter($member, $second->code);
        $permissions->forget();
        self::assertSame($second->id, $context->activeAccount($member)?->id);
        self::assertNull($context->activeAccount($owner));

        foreach ([[$owner, $second->code], [$withoutMembership, $second->code], [$platform, $first->code]] as [$user, $code]) {
            try {
                $context->enter($user, $code);
                self::fail('A conta sem vínculo não pode ser aberta.');
            } catch (NotFoundHttpException) {
                self::assertNull($context->activeAccount($user));
            }
        }

        self::assertTrue($platform->can('platform.accounts.list'));
        self::assertFalse($platform->can('account.tags.list'));
        self::assertTrue($personal->can('self.help.list'));
        self::assertFalse($personal->can('account.tags.list'));

        $this->app->forgetScopedInstances();
        self::assertNotSame($permissions, app(EffectivePermissions::class));
        self::assertNotSame($context, app(AccountContext::class));
    }
}
