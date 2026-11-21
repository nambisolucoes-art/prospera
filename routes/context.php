<?php

use App\Domains\Organizacao\Domain\DTOs\OrganizationCode;
use App\Domains\Organizacao\UI\Controllers\EstablishmentContextController;
use App\Domains\Pessoas\UI\Controllers\CommercialRelationController;
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

            // E07: relações comerciais das empresas das unidades da URL, sem repetir a empresa.
            Route::get('clientes', [CommercialRelationController::class, 'index'])
                ->defaults('role', 'customer')
                ->name('customers.index');

            Route::get('fornecedores', [CommercialRelationController::class, 'index'])
                ->defaults('role', 'supplier')
                ->name('suppliers.index');

            Route::get('clientes/cadastrar', [CommercialRelationController::class, 'create'])
                ->defaults('role', 'customer')
                ->name('customers.create');

            Route::get('fornecedores/cadastrar', [CommercialRelationController::class, 'create'])
                ->defaults('role', 'supplier')
                ->name('suppliers.create');

            foreach (['clientes' => 'customer', 'fornecedores' => 'supplier'] as $segment => $role) {
                Route::get($segment.'/{companyCode}/{relationId}/editar', [CommercialRelationController::class, 'edit'])
                    ->where('companyCode', OrganizationCode::ROUTE_PATTERN)
                    ->whereNumber('relationId')
                    ->defaults('role', $role)
                    ->name(($role === 'customer' ? 'customers' : 'suppliers').'.edit');
            }
        });

    // E07: escritas comerciais nomeiam a empresa responsável; nunca há empresa escolhida pelo sistema.
    Route::prefix('empresa/{empresa}')
        ->where(['empresa' => OrganizationCode::ROUTE_PATTERN])
        ->middleware('organization:company')
        ->name('company.')
        ->group(function () {
            foreach (['clientes' => 'customer', 'fornecedores' => 'supplier'] as $segment => $role) {
                $name = $role === 'customer' ? 'customers.' : 'suppliers.';

                Route::get($segment.'/{relationId}/visualizar', [CommercialRelationController::class, 'show'])
                    ->whereNumber('relationId')
                    ->defaults('role', $role)
                    ->name($name.'view');

                Route::post($segment.'/pessoa', [CommercialRelationController::class, 'findPerson'])
                    ->defaults('role', $role)
                    ->name($name.'find-person');

                Route::post($segment, [CommercialRelationController::class, 'store'])
                    ->defaults('role', $role)
                    ->name($name.'store');

                Route::patch($segment.'/{relationId}', [CommercialRelationController::class, 'update'])
                    ->whereNumber('relationId')
                    ->defaults('role', $role)
                    ->name($name.'update');

                Route::patch($segment.'/{relationId}/ativar', [CommercialRelationController::class, 'activate'])
                    ->whereNumber('relationId')
                    ->defaults('role', $role)
                    ->name($name.'activate');

                Route::patch($segment.'/{relationId}/inativar', [CommercialRelationController::class, 'deactivate'])
                    ->whereNumber('relationId')
                    ->defaults('role', $role)
                    ->name($name.'deactivate');
            }
        });
});
