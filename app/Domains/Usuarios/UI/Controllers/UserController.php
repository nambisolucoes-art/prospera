<?php

namespace App\Domains\Usuarios\UI\Controllers;

use App\Domains\Acessos\Application\Actions\SaveRoleAssignmentsAction;
use App\Domains\Acessos\Application\Actions\SendMembershipAccessLinkAction;
use App\Domains\Acessos\Application\Actions\SetMembershipStatusAction;
use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Application\Data\SaveRoleAssignmentsData;
use App\Domains\Acessos\Application\Queries\AssignmentNodeQuery;
use App\Domains\Acessos\Application\Queries\AssignmentOptionsQuery;
use App\Domains\Acessos\Application\Queries\MembershipDirectoryQuery;
use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Usuarios\Application\Actions\CreateUserAction;
use App\Domains\Usuarios\Application\Actions\SendUserInvitationAction;
use App\Domains\Usuarios\Application\Data\ProvisionUserData;
use App\Domains\Usuarios\Application\Queries\UserDirectoryQuery;
use App\Domains\Usuarios\Domain\Enums\PasswordSetup;
use App\Domains\Usuarios\Domain\Exceptions\IdentityCreationUnavailable;
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
        $pageSize = PageSize::fromInput($request->query('per_page'));

        return ApiPage::respond([
            'tenant' => false,
            'users' => $directory->list(null, $pageSize)->through(fn (User $user): array => [
                ...$user->only(['id', 'name', 'email']),
                'photo_url' => $user->profile_photo ? route(
                    'saas.users.photo',
                    ['userId' => $user->id, 'version' => $user->profile_photo],
                    absolute: false,
                ) : null,
            ]),
        ]);
    }

    public function tenantIndex(
        Request $request,
        MembershipDirectoryQuery $directory,
        EffectivePermissions $permissions,
    ): JsonResponse|View {
        $user = $request->user();
        $accountId = app(AccountContext::class)->requireId();
        $nodes = $permissions->nodesFor($user, 'account.users.list');
        abort_if($nodes->isEmpty(), 403);

        $manage = $permissions->nodesFor($user, 'account.users.assign-role');
        $view = $permissions->nodesFor($user, 'account.users.view');
        $search = mb_substr(trim($request->string('search')->toString()), 0, 100);
        $pageSize = PageSize::fromInput($request->query('per_page'));
        $memberships = $directory->list($accountId, $nodes, $pageSize, $search);

        return ApiPage::respond([
            'tenant' => true,
            'search' => $search,
            'users' => $memberships->through(fn (AccountMembership $membership) => [
                'id' => $membership->id,
                'person_id' => $membership->person_id,
                'name' => $membership->person->name,
                'email' => $membership->identity->email,
                'status' => $membership->status,
                'password_setup_pending' => $membership->identity->password_setup_pending,
                'photo_url' => $membership->identity->profile_photo
                    && ($view->tenant || $membership->assignments->contains(fn ($assignment) => $view->covers($assignment->node())))
                    ? route(
                        'account.users.photo',
                        ['userId' => $membership->user_id, 'version' => $membership->identity->profile_photo],
                        absolute: false,
                    ) : null,
                'assignments' => $membership->assignments->map($directory->assignment(...)),
                'can_manage' => $manage->tenant || $membership->assignments
                    ->contains(fn ($assignment) => $manage->covers($assignment->node())),
            ]),
        ]);
    }

    public function assignments(
        Request $request,
        MembershipDirectoryQuery $directory,
        AssignmentOptionsQuery $options,
        EffectivePermissions $permissions,
        int $membershipId,
    ): JsonResponse {
        $user = $request->user();
        $accountId = app(AccountContext::class)->requireId();
        $nodes = $permissions->nodesFor($user, 'account.users.assign-role');
        abort_if($nodes->isEmpty(), 403);

        $membership = $directory->visible($accountId, $nodes)->findOrFail($membershipId);

        return response()->json([
            'assignments' => $directory->assignmentRows($membership, $nodes, $user),
            'options' => $options->forOperator($user, $accountId),
        ]);
    }

    public function saveAssignments(
        Request $request,
        SaveRoleAssignmentsAction $save,
        int $membershipId,
    ): RedirectResponse {
        $accountId = app(AccountContext::class)->requireId();
        $save->execute($accountId, $membershipId, SaveRoleAssignmentsData::fromInput($request->all()));
        ApiPage::toast('Atribuições atualizadas.');

        return to_route('account.users.index');
    }

    public function suspend(SetMembershipStatusAction $status, int $membershipId): RedirectResponse
    {
        $status->execute(app(AccountContext::class)->requireId(), $membershipId, active: false);
        ApiPage::toast('Acesso à conta suspenso.');

        return to_route('account.users.index');
    }

    public function reactivate(SetMembershipStatusAction $status, int $membershipId): RedirectResponse
    {
        $status->execute(app(AccountContext::class)->requireId(), $membershipId, active: true);
        ApiPage::toast('Acesso à conta reativado.');

        return to_route('account.users.index');
    }

    public function sendPasswordReset(SendMembershipAccessLinkAction $send, int $membershipId): RedirectResponse
    {
        $membership = AccountMembership::where('account_id', app(AccountContext::class)->requireId())
            ->findOrFail($membershipId);
        $send->execute($membership, invitation: false);
        ApiPage::toast('O envio do link de redefinição foi solicitado.');

        return to_route('account.users.index');
    }

    public function resendInvitation(SendMembershipAccessLinkAction $send, int $membershipId): RedirectResponse
    {
        $membership = AccountMembership::where('account_id', app(AccountContext::class)->requireId())
            ->findOrFail($membershipId);
        $send->execute($membership, invitation: true);
        ApiPage::toast('O reenvio do convite foi solicitado.');

        return to_route('account.users.index');
    }

    public function create(Request $request, AssignmentOptionsQuery $options): JsonResponse|View
    {
        if (! $request->routeIs('account.users.*')) {
            return ApiPage::respond(['tenant' => false]);
        }

        $accountId = app(AccountContext::class)->requireId();
        $available = $options->forOperator($request->user(), $accountId, creating: true);
        abort_if($available === [], 403);

        return ApiPage::respond(['tenant' => true, 'assignmentOptions' => $available]);
    }

    public function store(
        Request $request,
        CreateUserAction $create,
        SendUserInvitationAction $invite,
        AssignmentNodeQuery $nodes,
    ): RedirectResponse {
        $tenant = $request->routeIs('account.users.*');
        $account = $tenant ? app(AccountContext::class)->requireAccount(404) : null;
        $data = ProvisionUserData::fromInput($request->all(), $tenant, $account?->id);
        $node = $account === null ? null : $nodes->resolve(
            $account->id,
            $data->scope,
            $data->companyId,
            $data->establishmentId,
            granting: true,
        );

        $message = 'Usuário criado.';

        try {
            $user = $create->execute($data->user, $data->role, $account, roleId: $data->roleId, node: $node);

            if ($data->user->setup === PasswordSetup::Invite) {
                $message = $invite->execute($user)
                    ? 'Usuário criado. O link para definir a senha foi enviado.'
                    : 'Usuário criado. O link pode ser solicitado em Esqueceu a senha.';
            }
        } catch (IdentityCreationUnavailable) {
            // A resposta do tenant é a mesma quando a identidade já existe.
        }

        ApiPage::toast($tenant ? 'Solicitação de acesso registrada.' : $message);

        return to_route($tenant ? 'account.users.index' : 'saas.users.index');
    }
}
