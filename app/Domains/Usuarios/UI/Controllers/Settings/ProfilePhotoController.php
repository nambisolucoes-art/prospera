<?php

declare(strict_types=1);

namespace App\Domains\Usuarios\UI\Controllers\Settings;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Application\Queries\MembershipDirectoryQuery;
use App\Domains\Plataforma\Application\Queries\AccountInspectionUsersQuery;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Domains\Usuarios\Application\Actions\SaveProfilePhotoAction;
use App\Domains\Usuarios\Application\Data\ProfilePhotoData;
use App\Domains\Usuarios\Application\Queries\UserDirectoryQuery;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProfilePhotoController extends Controller
{
    public function store(Request $request, SaveProfilePhotoAction $save): RedirectResponse
    {
        $save->execute($request->user(), ProfilePhotoData::fromFile($request->file('photo')));

        ApiPage::toast('Foto atualizada.');

        return to_route('profile.edit');
    }

    public function show(Request $request, string $version): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->profile_photo === $version, 404);

        return $this->image($user, $version);
    }

    public function showDirectory(
        Request $request,
        UserDirectoryQuery $directory,
        MembershipDirectoryQuery $memberships,
        EffectivePermissions $permissions,
        string $userId,
        string $version,
    ): StreamedResponse {
        if ($request->routeIs('account.users.*')) {
            $accountId = app(AccountContext::class)->requireId();
            $nodes = $permissions->nodesFor($request->user(), 'account.users.view');
            abort_if($nodes->isEmpty(), 403);
            $membership = $memberships->visible($accountId, $nodes)->where('user_id', $userId)->firstOrFail();
            $user = $membership->identity()->firstOrFail();
            abort_unless($user->profile_photo === $version, 404);
        } else {
            $user = $directory->findWithPhoto(null, (int) $userId, $version);
        }

        return $this->image($user, $version);
    }

    public function showInspection(
        Client $client,
        AccountInspectionUsersQuery $users,
        string $userId,
        string $version,
    ): StreamedResponse {
        abort_unless($client->active, 404);
        $user = $users->find($client, (int) $userId);
        abort_unless($user !== null && $user->profile_photo === $version, 404);

        return $this->image($user, $version);
    }

    private function image(User $user, string $version): StreamedResponse
    {
        $disk = Storage::disk('local');
        $path = User::profilePhotoPath($user->id, $version);
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, 'perfil.webp', [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
