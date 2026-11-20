<?php

namespace App\Domains\Plataforma\UI\Controllers;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Organizacao\Application\Queries\CompanyDirectoryQuery;
use App\Domains\Organizacao\Application\Queries\EstablishmentDirectoryQuery;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Plataforma\Application\Actions\SaveAccountAction;
use App\Domains\Plataforma\Application\Data\AccountFiltersData;
use App\Domains\Plataforma\Application\Data\SaveAccountData;
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

    public function accountDashboard(
        Request $request,
        CompanyDirectoryQuery $companies,
        EstablishmentDirectoryQuery $establishments,
    ): JsonResponse|View|RedirectResponse {
        if (! $request->user()->can('account.dashboard.view')) {
            // Sem acesso ao Início, a pessoa vai para a primeira tela que pode abrir.
            $fallbacks = [
                'account.people.list' => '/conta/pessoas',
                'account.tags.list' => '/conta/etiquetas',
                'account.companies.list' => '/conta/organizacao',
                'account.establishments.list' => '/conta/organizacao?nivel=estabelecimentos',
                'account.roles.list' => '/conta/cargos',
                'account.users.list' => '/conta/usuarios',
                'account.users.create' => '/conta/usuarios/cadastrar',
                'account.audit.list' => '/conta/auditoria',
                'account.people.create' => '/conta/pessoas/cadastrar',
                'account.tags.create' => '/conta/etiquetas/cadastrar',
                'account.roles.create' => '/conta/cargos/cadastrar',
                'self.profile.view' => '/settings/profile',
            ];

            $visible = app(EffectivePermissions::class)->visibleSlugsFor($request->user());

            foreach ($fallbacks as $ability => $path) {
                if (in_array($ability, $visible, true)) {
                    return redirect($path);
                }
            }

            abort(403);
        }

        $account = app(AccountContext::class)->requireAccount();

        $companyList = $request->user()->can('account.companies.list')
            ? $companies->options($account->id)
            : null;

        $establishmentCount = $request->user()->can('account.establishments.list')
            ? $establishments->count($account->id)
            : null;

        return ApiPage::respond([
            'account' => $account->only(['name']),
            'organization' => [
                'companies' => $companyList?->count(),
                'establishments' => $establishmentCount,
                // Empresas sem identificação fiscal precisam de revisão manual (provisionamento da E04).
                'incomplete' => $companyList
                    ?->filter(fn (Company $company) => $company->taxDocument() === null)
                    ->map(fn (Company $company) => ['id' => $company->id, 'name' => $company->name])
                    ->values(),
            ],
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
        ApiPage::toast('Dados da conta salvos.');

        if ($request->query('origem') === 'organizacao') {
            return to_route('account.organization', ['nivel' => 'conta']);
        }

        return to_route('account.dashboard');
    }

    public function save(Request $request, SaveAccountAction $save, ?Client $client = null): RedirectResponse
    {
        $data = SaveAccountData::fromInput($request->all(), $client);
        $message = $save->execute($client ?? new Client, $data);

        ApiPage::toast($message);

        return to_route('dashboard');
    }
}
