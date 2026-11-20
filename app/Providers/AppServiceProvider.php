<?php

namespace App\Providers;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Auditoria\Application\Actions\AuditContext;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(AuditContext::class);
        $this->app->scoped(AccountContext::class);
        $this->app->scoped(EffectivePermissions::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        foreach ([Login::class => 'login', Logout::class => 'logout'] as $event => $action) {
            Event::listen($event, function ($event) use ($action) {
                if (request()->routeIs('saas.accounts.inspection.start', 'account.inspection.end')) {
                    return;
                }
                $user = $event->user;
                if (! $user instanceof User) {
                    return;
                }
                $context = app(AuditContext::class);
                if ($context->actorId === null) {
                    $context->actorId = $user->id;
                    $context->actorName = $user->name;
                    $context->context = $user->is_saas_admin ? 'platform' : 'account';
                }
                AuditLog::record(
                    'user',
                    $user->id,
                    app(AccountContext::class)->linkedId($user),
                    $action,
                    $user->name,
                );
            });
        }

        Gate::define('access-saas', fn (User $user): bool => $user->is_saas_admin === true);
        Gate::before(function (User $user, string $ability): ?bool {
            if (! preg_match('/^(account|platform|self)\\.[a-z][a-z0-9-]*\\.[a-z][a-z0-9-]*$/', $ability)) {
                return null;
            }

            return app(EffectivePermissions::class)->allows($user, $ability);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
