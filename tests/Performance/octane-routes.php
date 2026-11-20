<?php

// Carregado somente na cópia descartável preparada pelo runner.
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Application\Context\OrganizationContext;
use App\Domains\Plataforma\Infrastructure\Session\AccountInspectionSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/_e03/session/{id?}', function (Request $request, ?int $id = null) {
    if ($id !== null) {
        Auth::login(User::findOrFail($id));
    }

    return ['token' => $request->session()->token()];
});

Route::get('/_e05/session-state', function (Request $request) {
    $session = $request->session();

    return [
        'id' => $session->getId(),
        'token' => $session->token(),
        'guard_user' => $request->user()?->id,
        'session_user' => $session->get(Auth::guard('web')->getName()),
        'inspecting' => AccountInspectionSession::current($session) !== null,
    ];
});

Route::get('/_e05/authorize', function (Request $request, EffectivePermissions $permissions) {
    $start = hrtime(true);
    $memory = memory_get_usage();
    $nodes = $permissions->nodesFor($request->user(), 'account.establishments.list');

    return [
        'covered' => ! $nodes->isEmpty(),
        'authorization_ms' => (hrtime(true) - $start) / 1e6,
        'authorization_bytes' => memory_get_usage() - $memory,
    ];
});

// E06: custo da resolução do contexto e da montagem do seletor, separados do restante da página.
Route::get('/_e06/context/{code}', function (Request $request, OrganizationContext $context, string $code) {
    $user = $request->user();
    $start = hrtime(true);
    $memory = memory_get_usage();
    $context->enterEstablishment($user, $code, false, true);
    $resolved = (hrtime(true) - $start) / 1e6;

    $start = hrtime(true);
    $units = $context->navigable($user);

    return [
        'resolve_ms' => $resolved,
        'selector_ms' => (hrtime(true) - $start) / 1e6,
        'context_bytes' => memory_get_usage() - $memory,
        'units' => count($units),
    ];
})->middleware('auth');
