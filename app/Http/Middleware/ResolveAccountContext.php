<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Plataforma\Infrastructure\Session\AccountInspectionSession;
use App\Support\ApiPage;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Aplicado ao grupo /contas/{conta} (E09). Roda depois de auth e de EnforceAccountInspection e antes do
 * contexto organizacional. A conta da URL vale só para esta requisição; outra aba pode usar outra conta.
 */
final readonly class ResolveAccountContext
{
    public function __construct(private AccountContext $accounts) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $code = (string) $request->route('conta');
        $inspection = $request->hasSession() ? AccountInspectionSession::current($request->session()) : null;

        try {
            $this->accounts->enter($user, $code, $inspection);
        } catch (HttpExceptionInterface $exception) {
            // A abertura direta recebe só o HTML inicial; a aplicação repete o pedido em JSON e orienta a pessoa.
            if ($request->isMethodSafe() && ! $request->expectsJson()) {
                $shell = ApiPage::respond();

                return $shell instanceof Response ? $shell : response($shell);
            }

            throw $exception;
        }

        // O gerador de URLs do Octane é clonado por requisição, então o padrão não passa para a seguinte.
        URL::defaults(['conta' => $code]);
        $request->route()?->forgetParameter('conta');

        return $next($request);
    }
}
