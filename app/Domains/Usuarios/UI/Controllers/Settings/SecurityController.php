<?php

namespace App\Domains\Usuarios\UI\Controllers\Settings;

use App\Domains\Usuarios\Application\Actions\UpdatePasswordAction;
use App\Domains\Usuarios\Application\Data\PasswordData;
use App\Http\Controllers\Controller;
use App\Support\ApiPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Laravel\Fortify\Features;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(Request $request): JsonResponse|View
    {
        $props = [
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
            'canManagePasskeys' => Features::canManagePasskeys(),
            'passkeys' => Features::canManagePasskeys()
                ? $request->user()
                    ->passkeys()
                    ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
                    ->latest()
                    ->get()
                    ->map(fn ($passkey) => [
                        'id' => $passkey->id,
                        'name' => $passkey->name,
                        'authenticator' => $passkey->authenticator,
                        'created_at_diff' => $passkey->created_at->diffForHumans(),
                        'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
                    ])
                    ->values()
                    ->all()
                : [],
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ];
        if (Features::canManageTwoFactorAuthentication()) {
            $props['twoFactorEnabled'] = $request->user()->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
            $props['twoFactorPending'] = $props['requiresConfirmation'] && ! $props['twoFactorEnabled']
                && $request->user()->two_factor_secret !== null;
        }

        return ApiPage::respond($props);
    }

    /**
     * Update the user's password.
     */
    public function update(Request $request, UpdatePasswordAction $update): RedirectResponse
    {
        $data = PasswordData::fromInput($request->all(), confirmCurrent: true);
        $update->execute($request->user(), $data);

        ApiPage::toast('Senha atualizada.');

        return back();
    }
}
