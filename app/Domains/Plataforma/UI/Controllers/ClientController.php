<?php

namespace App\Domains\Plataforma\UI\Controllers;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Plataforma\Application\Actions\SaveAccountAction;
use App\Domains\Plataforma\Application\Actions\SavePointAction;
use App\Domains\Plataforma\Application\Data\AccountFiltersData;
use App\Domains\Plataforma\Application\Data\SaveAccountData;
use App\Domains\Plataforma\Application\Data\SavePointData;
use App\Domains\Plataforma\Application\Queries\AccountDirectoryQuery;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Support\ApiPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

final class ClientController
{
    public function index(Request $request, AccountDirectoryQuery $directory): JsonResponse|View|RedirectResponse
    {
        if (! $request->user()->can('access-saas')) {
            abort_unless(app(AccountContext::class)->linkedId($request->user()) !== null, 403);

            return to_route('account.dashboard');
        }

        if (! $request->user()->hasVerifiedEmail()) {
            return to_route('verification.notice');
        }

        abort_unless($request->user()->can('platform.accounts.list'), 403);
        $filters = AccountFiltersData::fromInput($request->query());

        return ApiPage::respond([
            'accounts' => $directory->list($filters),
            'search' => $filters->search,
        ]);
    }

    public function create(): JsonResponse|View
    {
        return ApiPage::respond();
    }

    public function edit(Client $client): JsonResponse|View
    {
        return ApiPage::respond(['client' => $client]);
    }

    public function show(Client $client): RedirectResponse
    {
        return to_route('saas.accounts.edit', $client);
    }

    public function preview(Client $client): JsonResponse
    {
        $details = Arr::only($client->details ?? [], [
            'trade_name',
            'phone',
            'whatsapp',
            'phones',
            'address',
            'additional_phones',
            'additional_emails',
        ]);

        $administrator = $client->users()
            ->where('is_account_admin', true)
            ->orderBy('id')
            ->first(['name', 'email']);

        return response()->json([
            ...$client->only(['id', 'name', 'document', 'person_type', 'active', 'plan', 'email']),
            'details' => $details,
            'administrator' => $administrator,
        ]);
    }

    public function accountDashboard(Request $request): JsonResponse|View|RedirectResponse
    {
        if (! $request->user()->can('account.dashboard.view')) {
            // Sem acesso ao painel, a pessoa vai para a primeira tela que pode abrir.
            $fallbacks = [
                'account.people.list' => '/conta/pessoas',
                'account.tags.list' => '/conta/etiquetas',
                'account.roles.list' => '/conta/cargos',
                'account.people.create' => '/conta/pessoas/cadastrar',
                'account.tags.create' => '/conta/etiquetas/cadastrar',
                'account.roles.create' => '/conta/cargos/cadastrar',
                'self.profile.view' => '/settings/profile',
            ];

            foreach ($fallbacks as $ability => $path) {
                if ($request->user()->can($ability)) {
                    return redirect($path);
                }
            }

            abort(403);
        }

        $account = app(AccountContext::class)->requireAccount();

        $points = $request->user()->can('account.points.list')
            ? $account->points()->orderBy('name')->orderBy('id')->get(['id', 'name', 'active'])
            : [];

        return ApiPage::respond([
            'account' => $account->only(['name']),
            'points' => $points,
        ]);
    }

    public function editTenant(): JsonResponse|View
    {
        return ApiPage::respond(['client' => app(AccountContext::class)->requireAccount(), 'tenant' => true]);
    }

    public function updateTenant(Request $request, SaveAccountAction $save): RedirectResponse
    {
        $account = app(AccountContext::class)->requireAccount();
        $save->execute($account, SaveAccountData::fromTenantInput($request->all(), $account));
        ApiPage::toast('Dados do estabelecimento salvos.');

        return to_route('account.dashboard');
    }

    public function save(Request $request, SaveAccountAction $save, ?Client $client = null): RedirectResponse
    {
        $data = SaveAccountData::fromInput($request->all(), $client);
        $message = $save->execute($client ?? new Client, $data);

        ApiPage::toast($message);

        return to_route('dashboard');
    }

    public function savePoint(Request $request, SavePointAction $save, ?int $pointId = null): RedirectResponse
    {
        $account = app(AccountContext::class)->requireAccount();
        $point = $pointId === null ? null : $account->points()->findOrFail($pointId);
        $data = SavePointData::fromInput($request->all());
        $save->execute($account, $point, $data);

        ApiPage::toast('Ponto salvo.');

        return to_route('account.dashboard');
    }
}
