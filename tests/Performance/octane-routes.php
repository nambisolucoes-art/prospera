<?php

// Carregado somente na cópia descartável preparada pelo runner.
use App\Domains\Acessos\Application\Context\EffectivePermissions;
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
