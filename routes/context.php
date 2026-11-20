<?php

use App\Domains\Organizacao\Domain\DTOs\OrganizationCode;
use App\Domains\Organizacao\UI\Controllers\EstablishmentContextController;
use Illuminate\Support\Facades\Route;

// Rotas com contexto organizacional na URL (E06, DA-28). As rotas administrativas da conta seguem sem contexto.
// Módulos de empresa usam o grupo /conta/empresa/{empresa} com o middleware organization:company.
Route::middleware(['auth', 'verified'])->prefix('conta')->name('account.')->group(function () {
    Route::get('estabelecimento', [EstablishmentContextController::class, 'enter'])
        ->name('establishment.enter');

    Route::get('area-de-trabalho/padrao', [EstablishmentContextController::class, 'showDefault'])
        ->name('workspace.show-default');

    Route::put('area-de-trabalho/padrao', [EstablishmentContextController::class, 'saveDefault'])
        ->name('workspace.default');

    Route::prefix('estabelecimento/{estabelecimento}')
        ->where(['estabelecimento' => OrganizationCode::ROUTE_PATTERN])
        ->middleware('organization:establishment,consolidated')
        ->name('establishment.')
        ->group(function () {
            Route::get('/', [EstablishmentContextController::class, 'home'])
                ->name('home');
        });
});
