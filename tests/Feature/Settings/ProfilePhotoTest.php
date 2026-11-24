<?php

namespace Tests\Feature\Settings;

use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    #[RequiresPhpExtension('gd')]
    public function test_profile_photo_is_resized_private_and_replaced(): void
    {
        $disk = Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.photo.store'), ['photo' => UploadedFile::fake()->image('photo.jpg', 900, 600)])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $first = $user->refresh()->profile_photo;
        $this->assertNotNull($first);
        $path = User::profilePhotoPath($user->id, $first);
        $image = getimagesizefromstring($disk->get($path));
        $this->assertSame([320, 320, IMAGETYPE_WEBP], array_slice($image, 0, 3));

        $url = route('profile.photo.show', ['version' => $first]);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->actingAs(User::factory()->create())->get($url)->assertNotFound();

        $this->actingAs($user)
            ->post(route('profile.photo.store'), ['photo' => UploadedFile::fake()->image('new.png', 80, 80)])
            ->assertSessionHasNoErrors();

        $disk->assertMissing($path);
        $current = User::profilePhotoPath($user->id, $user->refresh()->profile_photo);
        $disk->assertExists($current);

        $this->delete(route('profile.destroy'), ['password' => 'password'])->assertSessionHasNoErrors();
        $disk->assertMissing($current);
    }

    public function test_profile_photo_rejects_non_image_uploads(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.photo.store'), ['photo' => UploadedFile::fake()->create('file.txt', 1, 'text/plain')])
            ->assertSessionHasErrors('photo');

        $this->assertNull($user->refresh()->profile_photo);
    }

    public function test_directory_photo_access_is_limited_to_users_visible_in_the_same_directory(): void
    {
        $disk = Storage::fake('local');
        $account = Client::query()->create(['name' => 'Conta A', 'active' => true]);
        $otherAccount = Client::query()->create(['name' => 'Conta B', 'active' => true]);
        $owner = User::factory()->asAccountAdministrator($account->id)->create(['name' => 'Zoe']);
        $member = User::factory()->forAccount($account->id)->create([
            'name' => 'Aline',
            'profile_photo' => (string) Str::uuid(),
        ]);
        $other = User::factory()->forAccount($otherAccount->id)->create(['profile_photo' => (string) Str::uuid()]);
        $platform = User::factory()->create([
            'name' => 'Aline',
            'is_saas_admin' => true,
            'profile_photo' => (string) Str::uuid(),
        ]);
        foreach ([$member, $other, $platform] as $user) {
            $disk->put(User::profilePhotoPath($user->id, $user->profile_photo), 'photo');
        }

        $photo = fn (string $scope, User $user) => route($scope.'.users.photo', [
            ...($scope === 'account' ? ['conta' => $account->code] : []),
            'userId' => $user->id, 'version' => $user->profile_photo,
        ], absolute: false);

        $this->actingAs($owner)->getJson('/conta/usuarios')
            ->assertJsonCount(2, 'users.data')
            ->assertJsonPath('users.data.0.photo_url', $photo('account', $member))
            ->assertJsonMissingPath('users.data.0.profile_photo')
            ->assertJsonPath('users.data.1.photo_url', null);
        $this->get($photo('account', $member))->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->get($photo('account', $other))->assertNotFound();
        $this->get($photo('saas', $platform))->assertForbidden();

        $this->actingAs($platform)->getJson(route('saas.users.index'))
            ->assertJsonPath('users.data.0.photo_url', $photo('saas', $platform));
        $this->get($photo('saas', $platform))->assertOk();
        $this->get($photo('saas', $member))->assertNotFound();

        $this->actingAs($member)->get($photo('account', $member))->assertForbidden();
    }
}
