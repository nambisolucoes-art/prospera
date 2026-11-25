<?php

declare(strict_types=1);

namespace App\Domains\Plataforma\UI\Controllers;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Organizacao\Application\Queries\CompanyDirectoryQuery;
use App\Domains\Organizacao\Application\Queries\EstablishmentDirectoryQuery;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Plataforma\Application\Actions\Accounts\CreateAccountAction;
use App\Domains\Plataforma\Application\Actions\Accounts\UpdateAccountAction;
use App\Domains\Plataforma\Application\Actions\Accounts\UpdateAccountProfileAction;
use App\Domains\Plataforma\Application\Data\Accounts\CreateAccountData;
use App\Domains\Plataforma\Application\Data\Accounts\ListAccountsData;
use App\Domains\Plataforma\Application\Data\Accounts\UpdateAccountData;
use App\Domains\Plataforma\Application\Data\Accounts\UpdateAccountProfileData;
use App\Domains\Plataforma\Application\Queries\AccountDirectoryQuery;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Domains\Plataforma\Infrastructure\Session\AccountInspectionSession;
use App\Support\ApiPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class ClientController
{
    public function index(Request $request, AccountDirectoryQuery $directory): JsonResponse|View|RedirectResponse
    {
        // Identidades de conta passam pela seleção, que entra direto quando há uma só conta disponível.
        if (! $request->user()->can('access-saas')) {
            return to_route('accounts.select');
        }

        if (! $request->user()->hasVerifiedEmail()) {
            return to_route('verification.notice');
        }

        abort_unless($request->user()->can('platform.accounts.list'), 403);
        $filters = ListAccountsData::fromInput($request->query());

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

    public function preview(Client $client, AccountDirectoryQuery $directory): JsonResponse
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

        return response()->json([
            ...$client->only(['id', 'name', 'document', 'person_type', 'active', 'plan', 'email']),
            'details' => $details,
            'administrator' => $directory->administrator($client->id),
        ]);
    }

    public function accountDashboard(
        Request $request,
        CompanyDirectoryQuery $companies,
        EstablishmentDirectoryQuery $establishments,
    ): JsonResponse|View|RedirectResponse {
        if (! $request->user()->can('account.dashboard.view')) {
            // Sem acesso ao Início, a pessoa vai para a primeira tela que pode abrir.
            $context = app(AccountContext::class);
            $fallbacks = [
                'account.tags.list' => $context->path('etiquetas'),
                'account.companies.list' => $context->path('organizacao'),
                'account.establishments.list' => $context->path('organizacao?nivel=estabelecimentos'),
                'account.roles.list' => $context->path('cargos'),
                'account.users.list' => $context->path('usuarios'),
                'account.users.create' => $context->path('usuarios/cadastrar'),
                'account.audit.list' => $context->path('auditoria'),
                'account.tags.create' => $context->path('etiquetas/cadastrar'),
                'account.roles.create' => $context->path('cargos/cadastrar'),
                'self.profile.view' => '/settings/profile',
                'self.help.list' => $context->path('ajuda'),
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

        // A última conta usada só ordena a seleção de contas; a inspeção não altera dados da pessoa.
        if ($request->expectsJson() && AccountInspectionSession::current($request->session()) === null) {
            $membershipId = app(AccountContext::class)->membershipId($request->user());

            DB::table('account_memberships')->where('id', $membershipId)->update(['last_used_at' => now()]);
        }

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

    public function updateTenant(Request $request, UpdateAccountProfileAction $action): RedirectResponse
    {
        $account = app(AccountContext::class)->requireAccount();

        $data = UpdateAccountProfileData::fromInput($request->all(), $account);

        $action->execute($data, $account);
        ApiPage::toast('Dados da conta salvos.');

        if ($request->query('origem') === 'organizacao') {
            return to_route('account.organization', ['nivel' => 'conta']);
        }

        return to_route('account.dashboard');
    }

    public function store(Request $request, CreateAccountAction $action): RedirectResponse
    {
        $data = CreateAccountData::fromInput($request->all());

        $message = $action->execute($data);
        ApiPage::toast($message);

        return to_route('dashboard');
    }

    public function update(Request $request, UpdateAccountAction $action, Client $client): RedirectResponse
    {
        $data = UpdateAccountData::fromInput($request->all(), $client);

        $action->execute($data, $client);
        ApiPage::toast('Conta salva.');

        return to_route('dashboard');
    }
}
