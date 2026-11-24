<?php

namespace App\Domains\Acessos\UI\Controllers;

use App\Domains\Plataforma\Application\Queries\AccountMembershipsQuery;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Domains\Plataforma\Infrastructure\Session\AccountInspectionSession;
use App\Support\ApiPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Seleção da conta (E09). Lista só os vínculos da própria identidade. A última conta usada apenas ordena
 * a lista; abrir qualquer conta passa de novo pela validação do vínculo em /contas/{conta}.
 */
final class AccountSelectionController
{
    private const DESTINATION = '/^[a-z0-9][a-z0-9\/-]{0,200}$/';

    public function index(Request $request, AccountMembershipsQuery $memberships): JsonResponse|View|RedirectResponse
    {
        $user = $request->user();

        if ($user->is_saas_admin) {
            return to_route('dashboard');
        }

        $inspected = $this->inspectedCode($request);

        if ($inspected !== null) {
            return redirect('/contas/'.$inspected);
        }

        $destination = $this->destination($request->query('destino'));
        $accounts = collect($memberships->forIdentity($user->id));
        $available = $accounts->where('status', 'active')->values();

        // Com uma conta disponível, a entrada é direta e a URL passa a mostrar a conta.
        if ($available->count() === 1) {
            return redirect($this->accountPath($available->first()['code'], $destination));
        }

        return ApiPage::respond([
            'destination' => $destination,
            'accounts' => $accounts->map(fn (array $account): array => [
                ...$account,
                'url' => $account['status'] === 'active' ? $this->accountPath($account['code'], $destination) : null,
            ])->all(),
        ]);
    }

    /**
     * Endereços /conta/... anteriores à E09, só em GET e HEAD. Sem ambiguidade, mantém o caminho na conta;
     * com várias contas, leva à seleção. Escritas nesses endereços não são aceitas.
     */
    public function legacy(Request $request, AccountMembershipsQuery $memberships, ?string $caminho = null): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->is_saas_admin, 404);

        $destination = $this->destination($caminho);
        $code = $this->inspectedCode($request);

        if ($code === null) {
            $available = collect($memberships->forIdentity($user->id))->where('status', 'active')->values();
            $code = $available->count() === 1 ? $available->first()['code'] : null;
        }

        if ($code === null) {
            return to_route('accounts.select', $destination === null ? [] : ['destino' => $destination]);
        }

        $query = $request->getQueryString();

        return redirect($this->accountPath($code, $destination).($query ? '?'.$query : ''));
    }

    private function inspectedCode(Request $request): ?string
    {
        $inspection = $request->hasSession() ? AccountInspectionSession::current($request->session()) : null;

        if ($inspection === null) {
            return null;
        }

        return Client::whereKey($inspection->accountId)->value('code');
    }

    private function destination(mixed $path): ?string
    {
        if (! is_string($path)) {
            return null;
        }

        $path = trim($path, '/');

        if ($path === '' || str_contains($path, '//') || preg_match(self::DESTINATION, $path) !== 1) {
            return null;
        }

        return $path;
    }

    private function accountPath(string $code, ?string $destination): string
    {
        return '/contas/'.$code.($destination === null ? '' : '/'.$destination);
    }
}
