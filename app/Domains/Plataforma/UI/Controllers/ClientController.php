<?php

namespace App\Domains\Plataforma\UI\Controllers;

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
use Illuminate\View\View;

final class ClientController
{
    public function index(Request $request, AccountDirectoryQuery $directory): JsonResponse|View|RedirectResponse
    {
        if (! $request->user()->can('access-saas')) {
            abort_unless($request->user()->account_id !== null, 403);

            return to_route('account.dashboard');
        }

        if (! $request->user()->hasVerifiedEmail()) {
            return to_route('verification.notice');
        }

        $filters = AccountFiltersData::fromInput($request->query());

        return ApiPage::respond(['accounts' => $directory->list($filters), 'search' => $filters->search]);
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
        $details = $client->details ?? [];

        return response()->json([
            ...$client->only(['id', 'name', 'document', 'person_type', 'active', 'plan', 'email']),
            'details' => array_intersect_key($details, array_flip(['trade_name', 'phone', 'whatsapp', 'phones', 'address', 'additional_phones', 'additional_emails'])),
            'administrator' => $client->users()->where('is_account_admin', true)->orderBy('id')->first(['name', 'email']),
        ]);
    }

    public function accountDashboard(Request $request): JsonResponse|View
    {
        $account = $this->currentAccount($request);

        return ApiPage::respond([
            'account' => $account->only(['name']),
            'points' => $account->points()->orderBy('name')->orderBy('id')->get(['id', 'name', 'active']),
        ]);
    }

    public function editTenant(Request $request): JsonResponse|View
    {
        return ApiPage::respond(['client' => $this->currentAccount($request), 'tenant' => true]);
    }

    public function updateTenant(Request $request, SaveAccountAction $save): RedirectResponse
    {
        $account = $this->currentAccount($request);
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
        $account = $this->currentAccount($request);
        $point = $pointId === null ? null : $account->points()->findOrFail($pointId);
        $data = SavePointData::fromInput($request->all());
        $save->execute($account, $point, $data);

        ApiPage::toast('Ponto salvo.');

        return to_route('account.dashboard');
    }

    private function currentAccount(Request $request): Client
    {
        $account = $request->user()->account()->where('active', true)->first();
        abort_unless($account !== null, 403);

        return $account;
    }
}
