<?php

declare(strict_types=1);

namespace App\Support;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Application\Context\OrganizationContext;
use App\Domains\Organizacao\Domain\ValueObjects\NavigableEstablishment;
use App\Domains\Plataforma\Application\Queries\Memberships\CountSelectableAccountsQuery;
use App\Domains\Plataforma\Infrastructure\Session\AccountInspectionSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ApiPage
{
    /** @param array<string, mixed> $data */
    public static function respond(array $data = []): JsonResponse|View
    {
        $request = request();
        if (! $request->expectsJson()) {
            $request->session()->keep(['toast', 'status']);

            return view('app');
        }

        $user = $request->user();
        $inspection = AccountInspectionSession::current($request->session());

        return response()->json([
            ...$data,
            'name' => config('app.name'),
            'auth' => [
                'permissions' => $user === null ? [] : app(EffectivePermissions::class)->visibleSlugsFor($user),
                'inspection_mode' => $inspection?->mode->value,
                // A troca de conta só aparece com mais de uma conta disponível e nunca durante a inspeção.
                'accounts' => $user === null || $user->is_saas_admin || $inspection !== null
                    ? 0
                    : app(CountSelectableAccountsQuery::class)->execute($user->id),
                'user' => $user ? [
                    ...$user->only(['id', 'name', 'email', 'email_verified_at', 'is_saas_admin']),
                    'photo_url' => $user->profile_photo
                        ? route('profile.photo.show', ['version' => $user->profile_photo], absolute: false)
                        : null,
                ] : null,
            ],
            'account_context' => self::account($request, $user),
            'organization_context' => self::organization($request, $user),
            'toast' => $request->session()->pull('toast'),
        ], headers: ['Cache-Control' => 'private, no-store']);
    }

    /**
     * Conta da URL validada pelo middleware account (E09). O frontend monta os endereços da conta com o
     * prefixo da própria página, nunca com um estado global.
     *
     * @return array{code: string, name: string, base: string}|null
     */
    private static function account(Request $request, ?User $user): ?array
    {
        $account = app(AccountContext::class)->activeAccount($user);

        if ($account === null || ! $request->routeIs('account.*')) {
            return null;
        }

        $tradeName = $account->details['trade_name'] ?? null;

        return [
            'code' => (string) $account->code,
            'name' => filled($tradeName) ? (string) $tradeName : $account->name,
            'base' => '/contas/'.$account->code,
        ];
    }

    /**
     * Contexto da página e unidades do seletor, só nas telas da conta.
     *
     * A lista é calculada a cada página para refletir revogações; o seletor nunca a usa como autorização.
     *
     * @return array<string, mixed>|null
     */
    private static function organization(Request $request, ?User $user): ?array
    {
        if ($user === null || ! $request->routeIs('account.*')) {
            return null;
        }

        $context = app(OrganizationContext::class);
        $units = $context->navigable($user);

        return [
            'account_id' => $units === [] ? null : $context->accountId(),
            'selected_codes' => $context->selectedCodes() ?: $context->defaultCodes($user),
            'mode' => $context->mode(),
            'company' => $context->company()?->toArray(),
            'establishment' => $context->establishment()?->toArray(),
            'consolidated' => $context->supportsConsolidated(),
            // Com uma unidade só, o seletor fica oculto e a lista não precisa ir para a página.
            'establishments' => count($units) > 1
                ? array_map(fn (NavigableEstablishment $unit) => $unit->toArray(), $units)
                : [],
        ];
    }

    public static function toast(string $message): void
    {
        session()->flash('toast', ['type' => 'success', 'message' => $message]);
    }
}
