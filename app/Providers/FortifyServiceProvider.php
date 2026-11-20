<?php

namespace App\Providers;

use App\Domains\Usuarios\UI\Fortify\ConfirmTwoFactorSetup;
use App\Domains\Usuarios\UI\Fortify\NeutralFailedPasswordResetResponse;
use App\Domains\Usuarios\UI\Fortify\NeutralPasswordLinkResponse;
use App\Domains\Usuarios\UI\Fortify\ResetUserPassword;
use App\Models\User;
use App\Support\ApiPage;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ConfirmTwoFactorAuthentication::class, ConfirmTwoFactorSetup::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, NeutralPasswordLinkResponse::class);
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, NeutralPasswordLinkResponse::class);
        $this->app->bind(FailedPasswordResetResponse::class, NeutralFailedPasswordResetResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', $request->input('email'))->where('access_enabled', true)->first();

            return $user && Hash::check($request->input('password'), $user->password) ? $user : null;
        });
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => ApiPage::respond([
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => ApiPage::respond([
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => ApiPage::respond([
            'status' => $request->session()->get('status'),
        ]));
        Fortify::verifyEmailView(fn (Request $request) => ApiPage::respond([
            'status' => $request->session()->get('status'),
        ]));
        Fortify::twoFactorChallengeView(fn () => ApiPage::respond());
        Fortify::confirmPasswordView(function (Request $request) {
            $intended = $request->session()->get('url.intended');
            $isSecurity = is_string($intended)
                && parse_url($intended, PHP_URL_PATH) === route('security.edit', absolute: false);

            return ApiPage::respond(['forSecurity' => $isSecurity]);
        });
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('access-recovery', fn (Request $request) => Limit::perMinute(6)->by($request->ip()));

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
