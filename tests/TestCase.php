<?php

namespace Tests;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Domains\Usuarios\Application\Actions\UpdatePasswordAction;
use App\Domains\Usuarios\Application\Data\PasswordData;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Features;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading();
    }

    public function call(
        $method,
        $uri,
        $parameters = [],
        $cookies = [],
        $files = [],
        $server = [],
        $content = null,
    ): TestResponse {
        // O cliente HTTP de testes reutiliza o container entre chamadas.
        $this->app->forgetScopedInstances();

        return parent::call($method, $this->accountPath($uri), $parameters, $cookies, $files, $server, $content);
    }

    /**
     * Convenção dos testes (E09): /conta/... é escrito em nome da conta do usuário autenticado e vira
     * /contas/{conta}/..., só quando ele tem exatamente um vínculo. Identidades em várias contas, visitantes
     * e os testes dos endereços anteriores usam a URL completa e não passam por esta conversão.
     */
    protected function accountPath(string $uri): string
    {
        if (preg_match('#^/conta(?=/|\?|$)#', $uri) !== 1) {
            return $uri;
        }

        $user = $this->app['auth']->guard('web')->user();

        if (! $user instanceof User) {
            return $uri;
        }

        $codes = AccountMembership::query()
            ->join('clients', 'clients.id', '=', 'account_memberships.account_id')
            ->where('account_memberships.user_id', $user->id)
            ->pluck('clients.code');

        if ($codes->count() !== 1) {
            return $uri;
        }

        return '/contas/'.$codes->first().substr($uri, strlen('/conta'));
    }

    protected function membership(User $user, ?int $accountId = null): AccountMembership
    {
        return $user->memberships()
            ->when($accountId !== null, fn ($query) => $query->where('account_id', $accountId))
            ->sole();
    }

    /**
     * Abre a conta para verificações diretas de Gate, como o middleware account faz numa requisição.
     * Retorna false quando a requisição real seria recusada (vínculo suspenso ou conta inativa).
     */
    protected function enterAccount(User $user, ?int $accountId = null): bool
    {
        $this->app->forgetScopedInstances();
        $code = Client::whereKey($accountId ?? $this->accountId($user))->value('code');

        try {
            $this->app->make(AccountContext::class)->enter($user, (string) $code);
        } catch (HttpExceptionInterface) {
            return false;
        }

        return true;
    }

    /** Conta do único vínculo da identidade, para os testes de uma conta só. */
    protected function accountId(User $user): int
    {
        return $this->membership($user)->account_id;
    }

    protected function completeInitialSetup(User $user): User
    {
        app(UpdatePasswordAction::class)->execute(
            $user,
            PasswordData::fromInput([
                'password' => 'Synthetic-password-123!',
                'password_confirmation' => 'Synthetic-password-123!',
            ]),
        );

        return $user->fresh();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
