<?php

use App\Domains\Plataforma\UI\Controllers\AccountInspectionController;
use App\Domains\Plataforma\UI\Controllers\ClientController;
use App\Domains\Usuarios\UI\Controllers\Settings\ProfilePhotoController;
use App\Domains\Usuarios\UI\Controllers\UserController;
use App\Support\ApiPage;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::post('/conta/inspecao/encerrar', [AccountInspectionController::class, 'end'])
        ->block()->name('account.inspection.end');
    Route::get('/dashboard', [ClientController::class, 'index'])->name('dashboard');
    Route::get('/conta', [ClientController::class, 'accountDashboard'])->middleware('verified')->name('account.dashboard');
    Route::middleware(['verified', 'can:manage-account'])->prefix('conta')->name('account.')->group(function () {
        Route::get('editar', [ClientController::class, 'editTenant'])->name('edit');
        Route::patch('/', [ClientController::class, 'updateTenant'])->name('update');
        Route::post('pontos', [ClientController::class, 'savePoint'])->name('points.store');
        Route::patch('pontos/{pointId}', [ClientController::class, 'savePoint'])->whereNumber('pointId')->name('points.update');
    });
    Route::middleware(['verified', 'can:manage-account-users'])->prefix('conta/usuarios')->name('account.users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('cadastrar', [UserController::class, 'create'])->name('create');
        Route::get('{userId}/foto/{version}', [ProfilePhotoController::class, 'showDirectory'])
            ->whereNumber('userId')->whereUuid('version')->name('photo');
        Route::post('/', [UserController::class, 'store'])->name('store');
    });
});

Route::middleware(['auth', 'can:access-saas', 'verified'])->group(function () {
    Route::prefix('saas/usuarios')->name('saas.users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('cadastrar', [UserController::class, 'create'])->name('create');
        Route::get('{userId}/foto/{version}', [ProfilePhotoController::class, 'showDirectory'])
            ->whereNumber('userId')->whereUuid('version')->name('photo');
        Route::post('/', [UserController::class, 'store'])->name('store');
    });

    Route::prefix('saas/contas')->name('saas.accounts.')->scopeBindings()->group(function () {
        Route::get('/', [ClientController::class, 'index'])->name('index');
        Route::get('cadastrar', [ClientController::class, 'create'])->name('create');
        Route::post('/', [ClientController::class, 'save'])->name('store');
        Route::get('{client}', [ClientController::class, 'show'])->name('show');
        Route::get('{client}/editar', [ClientController::class, 'edit'])->name('edit');
        Route::get('{client}/visualizar', [ClientController::class, 'preview'])->name('preview');
        Route::get('{client}/inspecao/usuarios', [AccountInspectionController::class, 'options'])->name('inspection.options');
        Route::get('{client}/inspecao/usuarios/{userId}/foto/{version}', [ProfilePhotoController::class, 'showInspection'])
            ->whereNumber('userId')->whereUuid('version')->name('inspection.photo');
        Route::post('{client}/inspecao', [AccountInspectionController::class, 'start'])->block()->name('inspection.start');
        Route::patch('{client}', [ClientController::class, 'save'])->name('update');
    });

    Route::redirect('saas/clientes/{client}', '/saas/contas/{client}');
    Route::get('{page}', fn () => ApiPage::respond())->where('page', '[a-z0-9][a-z0-9_-]*\.html');
});
