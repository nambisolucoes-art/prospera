<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class JsonNavigation
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (! $request->expectsJson()) {
            return $response;
        }

        if ($response instanceof RedirectResponse) {
            return $this->redirect($response->getTargetUrl());
        }

        if ($request->routeIs('security.edit') && $response->getStatusCode() === 423) {
            $request->session()->put('url.intended', $request->fullUrl());

            return $this->redirect(route('password.confirm'));
        }

        if ($request->isMethodSafe() || ! $response->isSuccessful()) {
            return $response;
        }

        $status = match ($request->route()?->getName()) {
            'password.email' => 'password-reset-link-sent',
            'verification.send' => 'verification-link-sent',
            'password.update' => 'Senha redefinida.',
            default => null,
        };
        if ($status !== null) {
            $request->session()->flash('status', $status);
        }

        $needsTwoFactor = $response instanceof JsonResponse && data_get($response->getData(true), 'two_factor') === true;
        $redirect = match ($request->route()?->getName()) {
            'login.store' => $needsTwoFactor
                ? route('two-factor.login') : $request->session()->pull('url.intended', route('dashboard')),
            'two-factor.login.store' => $request->session()->pull('url.intended', route('dashboard')),
            'password.confirm.store' => $request->session()->pull('url.intended', route('security.edit')),
            'password.update', 'logout' => route('login'),
            default => null,
        };

        return $redirect ? $this->redirect($redirect) : $response;
    }

    private function redirect(string $location): JsonResponse
    {
        return response()->json(['redirect' => $location], headers: ['Cache-Control' => 'private, no-store']);
    }
}
