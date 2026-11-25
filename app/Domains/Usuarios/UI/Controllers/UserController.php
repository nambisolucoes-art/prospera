<?php

declare(strict_types=1);

namespace App\Domains\Usuarios\UI\Controllers;

use App\Domains\Acessos\Application\Actions\ChangeRoleAssignmentsAction;
use App\Domains\Acessos\Application\Actions\CreateAccountInvitationAction;
use App\Domains\Acessos\Application\Actions\SendMembershipAccessLinkAction;
use App\Domains\Acessos\Application\Actions\SetMembershipStatusAction;
use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Application\Data\ChangeRoleAssignmentsData;
use App\Domains\Acessos\Application\Queries\AssignmentNodeQuery;
use App\Domains\Acessos\Application\Queries\AssignmentOptionsQuery;
use App\Domains\Acessos\Application\Queries\Invitations\ListPendingInvitationsQuery;
use App\Domains\Acessos\Application\Queries\Memberships\ListMembershipAssignmentsQuery;
use App\Domains\Acessos\Application\Queries\Memberships\ListVisibleMembershipsQuery;
use App\Domains\Acessos\Application\Queries\Memberships\PageMembershipsQuery;
use App\Domains\Acessos\Application\Queries\Memberships\ShowAssignmentQuery;
use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Pessoas\Application\Queries\People\ListAccessCandidatesQuery;
use App\Domains\Pessoas\Application\Queries\People\ListPeopleWithoutAccessQuery;
use App\Domains\Usuarios\Application\Actions\CreateUserAction;
use App\Domains\Usuarios\Application\Actions\SendUserInvitationAction;
use App\Domains\Usuarios\Application\Data\ProvisionUserData;
use App\Domains\Usuarios\Application\Queries\Users\ListUsersQuery;
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
    public function index(Request $request, ListUsersQuery $listUsers): JsonResponse|View
    {
        $pageSize = PageSize::fromInput($request->query('per_page'));

        return ApiPage::respond([
            'tenant' => false,
            'users' => $listUsers->execute(null, $pageSize)->through(fn (User $user): array => [
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
        PageMembershipsQuery $pageMemberships,
        ShowAssignmentQuery $showAssignment,
        EffectivePermissions $permissions,
        ListPendingInvitationsQuery $listPendingInvitations,
    ): JsonResponse|View {
        $user = $request->user();
        $accountId = app(AccountContext::class)->requireId();
        $nodes = $permissions->nodesFor($user, 'account.users.list');
        abort_if($nodes->isEmpty(), 403);

        $view = $permissions->nodesFor($user, 'account.users.view');
        $assign = $permissions->nodesFor($user, 'account.users.assign-role');
        $search = mb_substr(trim($request->string('search')->toString()), 0, 100);
        $pageSize = PageSize::fromInput($request->query('per_page'));
        $memberships = $pageMemberships->execute($accountId, $nodes, $pageSize, $search);

        $canRevokeInvitations = $request->user()->can('account.users.revoke-invitation');

        return ApiPage::respond([
            'tenant' => true,
            'search' => $search,
            'invitations' => $listPendingInvitations->execute($accountId, $nodes, $canRevokeInvitations),
            'users' => $memberships->through(function (AccountMembership $membership) use ($view, $assign, $showAssignment): array {
                $canView = $view->tenant || $membership->assignments
                    ->contains(fn ($assignment) => $view->covers($assignment->node()));

                return [
                    'id' => $membership->id,
                    'name' => $membership->person->name,
                    'email' => $membership->identity->email,
                    'status' => $membership->status,
                    'photo_url' => $canView && $membership->identity->profile_photo
                        ? route(
                            'account.users.photo',
                            ['userId' => $membership->user_id, 'version' => $membership->identity->profile_photo],
                            absolute: false,
                        ) : null,
                    'assignments' => $membership->assignments->map($showAssignment->execute(...)),
                    'can_view' => $canView,
                    'can_assign' => $assign->tenant || $membership->assignments
                        ->contains(fn ($assignment) => $assign->covers($assignment->node())),
                ];
            }),
        ]);
    }

    public function assignments(
        Request $request,
        ListVisibleMembershipsQuery $listVisibleMemberships,
        ListMembershipAssignmentsQuery $listMembershipAssignments,
        AssignmentOptionsQuery $options,
        EffectivePermissions $permissions,
        int $membershipId,
    ): JsonResponse {
        $user = $request->user();
        $accountId = app(AccountContext::class)->requireId();
        $nodes = $permissions->nodesFor($user, 'account.users.assign-role');
        abort_if($nodes->isEmpty(), 403);

        $membership = $listVisibleMemberships->execute($accountId, $nodes)->findOrFail($membershipId);

        return response()->json([
            'assignments' => $listMembershipAssignments->execute($membership, $nodes, $user),
            'options' => $options->forOperator($user, $accountId),
        ]);
    }

    public function saveAssignments(
        Request $request,
        ChangeRoleAssignmentsAction $save,
        int $membershipId,
    ): RedirectResponse {
        $accountId = app(AccountContext::class)->requireId();
        $save->execute(ChangeRoleAssignmentsData::fromInput($request->all()), $accountId, $membershipId);
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

    public function create(
        Request $request,
        AssignmentOptionsQuery $options,
        EffectivePermissions $permissions,
    ): JsonResponse|View {
        if (! $request->routeIs('account.users.*')) {
            return ApiPage::respond(['tenant' => false]);
        }

        $accountId = app(AccountContext::class)->requireId();
        $available = $options->forOperator($request->user(), $accountId, creating: true);
        abort_if($available === [], 403);

        return ApiPage::respond([
            'tenant' => true,
            'assignmentOptions' => $available,
            'canReusePerson' => ! $permissions->nodesFor($request->user(), 'account.people.view')->isEmpty(),
        ]);
    }

    public function peopleForAccess(
        Request $request,
        AssignmentOptionsQuery $options,
        EffectivePermissions $permissions,
        ListAccessCandidatesQuery $listAccessCandidates,
    ): JsonResponse {
        $accountId = app(AccountContext::class)->requireId();
        abort_if($permissions->nodesFor($request->user(), 'account.people.view')->isEmpty(), 403);
        abort_if($options->forOperator($request->user(), $accountId, creating: true) === [], 403);

        $input = $request->query('q');
        $search = is_string($input) ? mb_substr(trim($input), 0, 100) : '';
        $candidates = $search !== '' ? $listAccessCandidates->execute($accountId, $search) : [];

        return response()->json(['people' => $candidates], headers: ['Cache-Control' => 'private, no-store']);
    }

    public function store(
        Request $request,
        CreateUserAction $create,
        SendUserInvitationAction $invite,
        CreateAccountInvitationAction $inviteExisting,
        AssignmentNodeQuery $nodes,
        EffectivePermissions $permissions,
        ListPeopleWithoutAccessQuery $listPeopleWithoutAccess,
    ): RedirectResponse {
        $tenant = $request->routeIs('account.users.*');
        $account = $tenant ? app(AccountContext::class)->requireAccount(404) : null;
        $input = $request->all();
        $person = null;
        $personId = $tenant ? ProvisionUserData::personId(
            $input['person_id'] ?? null,
            $input['person_registration'] ?? null,
        ) : null;

        if ($personId !== null && $account !== null) {
            abort_if($permissions->nodesFor($request->user(), 'account.people.view')->isEmpty(), 403);
            $person = $listPeopleWithoutAccess->execute($account->id)->findOrFail($personId);
            $input['name'] = $person->name;
            $input['email'] ??= $person->email;
        }

        $data = ProvisionUserData::fromInput($input, $tenant, $account?->id);
        $node = $account === null ? null : $nodes->resolve(
            $account->id,
            $data->scope,
            $data->companyId,
            $data->establishmentId,
            granting: true,
        );

        $message = 'Usuário criado.';

        try {
            $user = $create->execute($data->user, $data->role, $account, person: $person, roleId: $data->roleId, node: $node);

            if ($data->user->setup === PasswordSetup::Invite) {
                $message = $invite->execute($user)
                    ? 'Usuário criado. O link para definir a senha foi enviado.'
                    : 'Usuário criado. O link pode ser solicitado em Esqueceu a senha.';
            }
        } catch (IdentityCreationUnavailable) {
            // Identidade existente: convite para aceitar nesta conta (E09). A resposta é a mesma de sempre.
            if ($account !== null && $node !== null && $data->roleId !== null) {
                $inviteExisting->execute($account, $data->user->email, $data->roleId, $node, $person);
            }
        }

        ApiPage::toast($tenant ? 'Solicitação de acesso registrada.' : $message);

        return to_route($tenant ? 'account.users.index' : 'saas.users.index');
    }
}
