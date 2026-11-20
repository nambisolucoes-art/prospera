<?php

namespace App\Support;

use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Application\Context\OrganizationContext;
use App\Domains\Organizacao\Domain\DTOs\NavigableEstablishment;
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

        return response()->json([
            ...$data,
            'name' => config('app.name'),
            'auth' => [
                'permissions' => $user === null ? [] : app(EffectivePermissions::class)->visibleSlugsFor($user),
                'inspection_mode' => AccountInspectionSession::current($request->session())?->mode->value,
                'user' => $user ? [
                    ...$user->only(['id', 'name', 'email', 'email_verified_at', 'is_saas_admin']),
                    'photo_url' => $user->profile_photo
                        ? route('profile.photo.show', ['version' => $user->profile_photo], absolute: false)
                        : null,
                ] : null,
            ],
            'organization_context' => self::organization($request, $user),
            'toast' => $request->session()->pull('toast'),
        ], headers: ['Cache-Control' => 'private, no-store']);
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
