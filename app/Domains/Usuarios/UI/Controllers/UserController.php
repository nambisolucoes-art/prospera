<?php

namespace App\Domains\Usuarios\UI\Controllers;

use App\Domains\Acessos\Application\Actions\AssignRoleAction;
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
use Illuminate\Support\Facades\Gate;
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
                    'person_id' => $user->person_id,
                    'role_name' => $user->role?->name,
                    'photo_url' => $user->profile_photo ? route(
                        $tenant ? 'account.users.photo' : 'saas.users.photo',
                        ['userId' => $user->id, 'version' => $user->profile_photo],
                        absolute: false,
                    ) : null,
                ]),
        ]);
    }

    public function create(Request $request): JsonResponse|View|RedirectResponse
    {
        if ($request->routeIs('account.users.*')) {
            return redirect('/conta/pessoas/cadastrar');
        }

        return ApiPage::respond(['tenant' => false]);
    }

    public function store(
        Request $request,
        CreateUserAction $create,
        SendUserInvitationAction $invite,
    ): RedirectResponse {
        $tenant = $request->routeIs('account.users.*');
        $data = ProvisionUserData::fromInput($request->all(), $tenant, $tenant ? $request->user()->account_id : null);
        $account = $tenant ? $request->user()->account()->where('active', true)->firstOrFail() : null;
        if ($tenant) {
            Gate::authorize('account.people.create');
            Gate::authorize('account.users.assign-role');
            app(AssignRoleAction::class)->assertAssignable($account->id, $data->roleId);
        }
        $user = $create->execute($data->user, $data->role, $account, roleId: $data->roleId);

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
