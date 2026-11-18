<?php

namespace App\Domains\Usuarios\UI\Controllers;

use App\Domains\Usuarios\Application\Actions\CreateUserAction;
use App\Domains\Usuarios\Application\Actions\SendUserInvitationAction;
use App\Domains\Usuarios\Application\Data\ProvisionUserData;
use App\Domains\Usuarios\Application\Queries\UserDirectoryQuery;
use App\Domains\Usuarios\Domain\Enums\PasswordSetup;
use App\Models\User;
use App\Support\ApiPage;
use App\Support\PageSize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class UserController
{
    public function index(Request $request, UserDirectoryQuery $directory): JsonResponse|View
    {
        $tenant = $request->routeIs('account.users.*');
        $account = $tenant ? $request->user()->account()->firstOrFail() : null;

        return ApiPage::respond([
            'tenant' => $tenant,
            'users' => $directory->list($account, PageSize::fromInput($request->query('per_page')))
                ->through(fn (User $user): array => [
                    ...$user->only(['id', 'name', 'email', 'is_account_admin']),
                    'photo_url' => $user->profile_photo ? route(
                        $tenant ? 'account.users.photo' : 'saas.users.photo',
                        ['userId' => $user->id, 'version' => $user->profile_photo],
                        absolute: false,
                    ) : null,
                ]),
        ]);
    }

    public function create(Request $request): JsonResponse|View
    {
        return ApiPage::respond(['tenant' => $request->routeIs('account.users.*')]);
    }

    public function store(Request $request, CreateUserAction $create, SendUserInvitationAction $invite): RedirectResponse
    {
        $tenant = $request->routeIs('account.users.*');
        $data = ProvisionUserData::fromInput($request->all(), $tenant);
        $account = $tenant ? $request->user()->account()->where('active', true)->firstOrFail() : null;
        $user = $create->execute($data->user, $data->role, $account);

        $message = 'Usuário criado.';
        if ($data->user->setup === PasswordSetup::Invite) {
            $message = $invite->execute($user)
                ? 'Usuário criado. O link para definir a senha foi enviado.'
                : 'Usuário criado. O link pode ser solicitado em Esqueceu a senha.';
        }

        ApiPage::toast($message);

        return to_route($tenant ? 'account.users.index' : 'saas.users.index');
    }
}
