<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureIdentitySessionCurrent
{
    public const KEY = 'identity_session_revision';

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $identity = $request->user();

        if ($identity === null || ! $request->hasSession()) {
            return $next($request);
        }

        $session = $request->session();
        $stored = $session->get(self::KEY);
        $revision = $identity->emailChangeRevision();

        if (($stored['id'] ?? null) === $identity->id && ($stored['value'] ?? null) !== $revision) {
            Auth::guard('web')->logoutCurrentDevice();
            $session->invalidate();
            $session->regenerateToken();

            return $request->expectsJson() ? response()->json(['message' => 'Sessão encerrada.'], 401) : to_route('login');
        }

        $session->put(self::KEY, ['id' => $identity->id, 'value' => $revision]);

        return $next($request);
    }
}
