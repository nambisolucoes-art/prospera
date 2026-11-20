<?php

namespace App\Http\Middleware;

use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Plataforma\Application\Queries\AccountInspectionUsersQuery;
use App\Domains\Plataforma\Domain\Enums\AccountInspectionMode;
use App\Domains\Plataforma\Infrastructure\Session\AccountInspectionSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnforceAccountInspection
{
    public function __construct(private AccountInspectionUsersQuery $users) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $inspection = $request->hasSession() ? AccountInspectionSession::current($request->session()) : null;
        if ($inspection === null) {
            return $next($request);
        }

        $user = $request->user();
        if (
            $this->users->administrator($inspection->administratorId) === null
            || $user === null
            || $user->id !== $inspection->userId
            || ! AccountMembership::where('user_id', $user->id)
                ->where('account_id', $inspection->accountId)
                ->whereKey($inspection->membershipId)
                ->where('status', 'active')
                ->exists()
            || $user->is_saas_admin
            || ! $user->hasVerifiedEmail()
        ) {
            Auth::guard('web')->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => 'Sessão de inspeção encerrada.'], 401)
                : to_route('login');
        }

        if ($request->routeIs('account.inspection.end', 'logout')) {
            return $next($request);
        }

        if ($request->isMethodSafe()) {
            abort_if($request->routeIs(
                'profile.email.confirm', 'verification.verify', 'two-factor.qr-code', 'two-factor.secret-key', 'two-factor.recovery-codes',
            ), 403);

            return $next($request);
        }

        abort_unless($inspection->mode === AccountInspectionMode::Edit && $request->routeIs('account.*'),
            403, 'Esta operação não está disponível neste modo de inspeção.');

        $response = $next($request);
        Log::notice('Operação durante inspeção de conta.', [
            ...AccountInspectionSession::context($inspection),
            'route' => $request->route()?->getName(),
            'response_status' => $response->getStatusCode(),
        ]);

        return $response;
    }
}
