<?php

namespace Tests\Feature;

use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountInspectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_platform_can_select_eligible_users_from_the_account(): void
    {
        $account = Client::query()->create(['name' => 'Conta ativa', 'active' => true]);
        $inactive = Client::query()->create(['name' => 'Conta inativa', 'active' => false]);
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $member = User::factory()->create(['account_id' => $account->id, 'profile_photo' => (string) Str::uuid()]);
        User::factory()->unverified()->create(['account_id' => $account->id]);
        $outsider = User::factory()->create(['account_id' => $inactive->id]);

        $options = route('saas.accounts.inspection.options', $account);
        $start = route('saas.accounts.inspection.start', $account);
        $photo = fn (Client $client) => route('saas.accounts.inspection.photo', [
            'client' => $client, 'userId' => $member->id, 'version' => $member->profile_photo,
        ], absolute: false);
        Storage::fake('local')->put(User::profilePhotoPath($member->id, $member->profile_photo), 'photo');

        $this->actingAs($member)->getJson($options)->assertForbidden();
        $this->get($photo($account))->assertForbidden();
        $this->post($start, ['user_id' => $member->id, 'mode' => 'edit'])->assertForbidden();
        $this->actingAs($platform)->getJson($options)->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.id', $member->id)->assertJsonPath('0.photo_url', $photo($account));
        $this->get($photo($account))->assertOk()->assertHeader('Content-Type', 'image/webp');
        $otherActive = Client::query()->create(['name' => 'Outra conta', 'active' => true]);
        $this->get($photo($otherActive))->assertNotFound();
        $this->getJson(route('saas.accounts.inspection.options', $inactive))->assertNotFound();
        $this->postJson($start, ['user_id' => $outsider->id, 'mode' => 'edit'])
            ->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->postJson($start, ['user_id' => $member->id, 'mode' => 'unsupported'])
            ->assertUnprocessable()->assertJsonValidationErrors('mode');
        $this->assertAuthenticatedAs($platform);
    }

    public function test_read_only_inspection_shows_the_account_and_restores_the_administrator(): void
    {
        $account = Client::query()->create(['name' => 'Conta ativa', 'active' => true]);
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $owner = User::factory()->create(['account_id' => $account->id, 'is_account_admin' => true]);

        $this->actingAs($platform)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('saas.accounts.inspection.start', $account), ['user_id' => $owner->id, 'mode' => 'read-only'])
            ->assertRedirect(route('account.dashboard'))->assertSessionMissing('auth.password_confirmed_at');
        $this->assertAuthenticatedAs($owner);
        $this->getJson(route('account.dashboard'))->assertJsonPath('auth.inspection_mode', 'read-only')->assertJsonPath('auth.user.id', $owner->id)
            ->assertJsonPath('account.name', 'Conta ativa');
        $this->getJson(route('two-factor.secret-key'))->assertForbidden();
        $this->post(route('account.points.store'), ['name' => 'Ponto de teste', 'active' => '1'])->assertForbidden();
        $this->assertDatabaseMissing('points', ['name' => 'Ponto de teste']);
        $this->get(route('saas.users.index'))->assertForbidden();

        $this->post(route('account.inspection.end'))->assertRedirect(route('dashboard'))->assertSessionMissing('account_inspection');
        $this->assertAuthenticatedAs($platform);
        $this->post(route('account.inspection.end'))->assertForbidden();
    }

    public function test_edit_inspection_uses_the_selected_users_permissions_and_keeps_personal_settings_protected(): void
    {
        $account = Client::query()->create(['name' => 'Conta ativa', 'active' => true]);
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $owner = User::factory()->create(['account_id' => $account->id, 'is_account_admin' => true]);
        $member = User::factory()->create(['account_id' => $account->id]);
        $start = route('saas.accounts.inspection.start', $account);
        $end = route('account.inspection.end');

        $this->actingAs($platform)->post($start, ['user_id' => $member->id, 'mode' => 'edit'])->assertRedirect();
        $this->post(route('account.points.store'), ['name' => 'Ponto de membro', 'active' => '1'])->assertForbidden();
        $this->post($end)->assertRedirect(route('dashboard'));

        $this->post($start, ['user_id' => $owner->id, 'mode' => 'edit'])->assertRedirect();
        $this->post(route('account.points.store'), ['name' => 'Ponto de administrador', 'active' => '1'])
            ->assertRedirect(route('account.dashboard'));
        $this->assertSame('Ponto de administrador', $account->points()->sole()->name);
        $this->patch(route('profile.update'), ['name' => 'Alterado', 'email' => $owner->email])->assertForbidden();

        $platform->forceFill(['is_saas_admin' => false])->saveOrFail();
        $this->post($end)->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
