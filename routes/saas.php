<?php

use App\Domains\Plataforma\UI\Controllers\AccountInspectionController;
use App\Domains\Plataforma\UI\Controllers\ClientController;
use App\Domains\Usuarios\UI\Controllers\Settings\ProfilePhotoController;
use App\Domains\Usuarios\UI\Controllers\UserController;
use App\Support\ApiPage;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [ClientController::class, 'index'])->name('dashboard');
});

Route::middleware(['auth', 'can:access-saas', 'verified'])->group(function () {
    Route::prefix('saas/usuarios')->name('saas.users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])
            ->name('index')
            ->middleware('can:platform.users.list');

        Route::get('cadastrar', [UserController::class, 'create'])
            ->name('create')
            ->middleware('can:platform.users.create');

        Route::get('{userId}/foto/{version}', [ProfilePhotoController::class, 'showDirectory'])
            ->whereNumber('userId')
            ->whereUuid('version')
            ->name('photo')
            ->middleware('can:platform.users.view');

        Route::post('/', [UserController::class, 'store'])
            ->name('store')
            ->middleware('can:platform.users.create');
    });

    Route::prefix('saas/contas')->name('saas.accounts.')->scopeBindings()->group(function () {
        Route::get('/', [ClientController::class, 'index'])
            ->name('index')
            ->middleware('can:platform.accounts.list');

        Route::get('cadastrar', [ClientController::class, 'create'])
            ->name('create')
            ->middleware('can:platform.accounts.create');

        Route::post('/', [ClientController::class, 'save'])
            ->name('store')
            ->middleware('can:platform.accounts.create');

        Route::get('{client}', [ClientController::class, 'show'])
            ->name('show')
            ->middleware('can:platform.accounts.view');

        Route::get('{client}/editar', [ClientController::class, 'edit'])
            ->name('edit')
            ->middleware('can:platform.accounts.update');

        Route::get('{client}/visualizar', [ClientController::class, 'preview'])
            ->name('preview')
            ->middleware('can:platform.accounts.view');

        Route::get('{client}/inspecao/usuarios', [AccountInspectionController::class, 'options'])
            ->name('inspection.options')
            ->middleware('can:platform.inspection.list');

        Route::get(
            '{client}/inspecao/usuarios/{userId}/foto/{version}',
            [ProfilePhotoController::class, 'showInspection'],
        )
            ->whereNumber('userId')
            ->whereUuid('version')
            ->name('inspection.photo')
            ->middleware('can:platform.inspection.list');

        Route::post('{client}/inspecao', [AccountInspectionController::class, 'start'])
            ->block()
            ->name('inspection.start')
            ->middleware('can:platform.inspection.start');

        Route::patch('{client}', [ClientController::class, 'save'])
            ->name('update')
            ->middleware('can:platform.accounts.update');
    });

    Route::redirect('saas/clientes/{client}', '/saas/contas/{client}');
    Route::get('{page}', fn () => ApiPage::respond())->where('page', '[a-z0-9][a-z0-9_-]*\.html');
});
