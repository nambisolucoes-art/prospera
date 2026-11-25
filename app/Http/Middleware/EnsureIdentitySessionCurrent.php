<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Session\Session;
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
        $revision = $identity->emailChangeRevision();

        if (self::isStale($identity, $session, $revision)) {
            Auth::guard('web')->logoutCurrentDevice();
            $session->invalidate();
            $session->regenerateToken();

            return $request->expectsJson() ? response()->json(['message' => 'Sessão encerrada.'], 401) : to_route('login');
        }

        $session->put(self::KEY, ['id' => $identity->id, 'value' => $revision]);

        return $next($request);
    }

    public static function isStale(User $identity, Session $session, ?string $revision = null): bool
    {
        $stored = $session->get(self::KEY);

        return ($stored['id'] ?? null) === $identity->id
            && ($stored['value'] ?? null) !== ($revision ?? $identity->emailChangeRevision());
    }
}
