<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class SelfServicePermissions
{
    public function handle(Request $request, Closure $next): Response
    {
        $permissions = [
            'passkey.store' => 'self.security.passkey-create',
            'passkey.registration-options' => 'self.security.passkey-create',
            'passkey.destroy' => 'self.security.passkey-delete',
            'two-factor.enable' => 'self.security.two-factor-enable',
            'two-factor.confirm' => 'self.security.two-factor-confirm',
            'two-factor.disable' => 'self.security.two-factor-disable',
            'two-factor.qr-code' => 'self.security.two-factor-view',
            'two-factor.secret-key' => 'self.security.two-factor-view',
            'two-factor.recovery-codes' => 'self.security.recovery-view',
            'two-factor.regenerate-recovery-codes' => 'self.security.recovery-generate',
        ];

        if ($request->user() && isset($permissions[$request->route()?->getName()])) {
            Gate::authorize($permissions[$request->route()->getName()]);
        }

        return $next($request);
    }
}
