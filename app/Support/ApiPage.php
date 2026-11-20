<?php

namespace App\Support;

use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Plataforma\Infrastructure\Session\AccountInspectionSession;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

final class ApiPage
{
    /** @param array<string, mixed> $data */
    public static function respond(array $data = []): JsonResponse|View
    {
        $request = request();
        if (! $request->expectsJson()) {
            $request->session()->keep(['toast', 'status']);

            return view('app');
        }

        $user = $request->user();

        return response()->json([
            ...$data,
            'name' => config('app.name'),
            'auth' => [
                'permissions' => $user === null ? [] : app(EffectivePermissions::class)->visibleSlugsFor($user),
                'inspection_mode' => AccountInspectionSession::current($request->session())?->mode->value,
                'user' => $user ? [
                    ...$user->only(['id', 'name', 'email', 'email_verified_at', 'is_saas_admin']),
                    'photo_url' => $user->profile_photo
                        ? route('profile.photo.show', ['version' => $user->profile_photo], absolute: false)
                        : null,
                ] : null,
            ],
            'toast' => $request->session()->pull('toast'),
        ], headers: ['Cache-Control' => 'private, no-store']);
    }

    public static function toast(string $message): void
    {
        session()->flash('toast', ['type' => 'success', 'message' => $message]);
    }
}
