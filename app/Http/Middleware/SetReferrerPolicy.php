<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Impede que o navegador envie a URL da página como Referer, inclusive para o próprio servidor.
 * Convites, redefinição de senha e links assinados carregam credenciais temporárias no caminho ou na query.
 */
final class SetReferrerPolicy
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
