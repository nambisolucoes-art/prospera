<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class EnsureUserAccessEnabled
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->user() && ! $request->user()->access_enabled) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => 'Acesso revogado.'], 401)
                : redirect('/login');
        }

        return $next($request);
    }
}
