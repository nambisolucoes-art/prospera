<?php

// Carregado somente na cópia descartável preparada pelo runner.
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
