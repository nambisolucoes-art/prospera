<?php

use App\Domains\Acessos\UI\Controllers\AccessController;
use App\Domains\Organizacao\UI\Controllers\CompanyController;
use App\Domains\Organizacao\UI\Controllers\EstablishmentController;
use App\Domains\Organizacao\UI\Controllers\OrganizationController;
use App\Domains\Pessoas\UI\Controllers\DirectoryController;
use App\Domains\Plataforma\UI\Controllers\AccountInspectionController;
use App\Domains\Plataforma\UI\Controllers\ClientController;
use App\Domains\Usuarios\UI\Controllers\Settings\ProfilePhotoController;
use App\Domains\Usuarios\UI\Controllers\UserController;
use App\Support\ApiPage;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::post('/conta/inspecao/encerrar', [AccountInspectionController::class, 'end'])
        ->block()
        ->name('account.inspection.end');

    Route::get('/dashboard', [ClientController::class, 'index'])->name('dashboard');
    Route::get('/conta', [ClientController::class, 'accountDashboard'])
        ->middleware('verified')
        ->name('account.dashboard');

    Route::middleware(['verified'])->prefix('conta')->name('account.')->group(function () {
        Route::get('organizacao', OrganizationController::class)->name('organization');

        Route::get('editar', [ClientController::class, 'editTenant'])
            ->name('edit')
            ->middleware('can:account.account-data.update');

        Route::patch('/', [ClientController::class, 'updateTenant'])
            ->name('update')
            ->middleware('can:account.account-data.update');
    });
    Route::middleware(['verified'])->prefix('conta/usuarios')->name('account.users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])
            ->name('index')
            ->middleware('can:account.users.list');

        Route::get('cadastrar', [UserController::class, 'create'])
            ->name('create')
            ->middleware('can:account.users.create');

        Route::get('{userId}/foto/{version}', [ProfilePhotoController::class, 'showDirectory'])
            ->whereNumber('userId')
            ->whereUuid('version')
            ->name('photo')
            ->middleware('can:account.users.view');

        Route::post('/', [UserController::class, 'store'])
            ->name('store')
            ->middleware('can:account.users.create');
    });
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

Route::middleware(['auth', 'verified'])->prefix('conta')->name('account.directory.')->group(function () {
    $controller = DirectoryController::class;

    Route::get('pessoas', [$controller, 'people'])
        ->name('people.index')
        ->middleware('can:account.people.list');

    Route::get('pessoas/cadastrar', [$controller, 'personForm'])
        ->name('people.create')
        ->middleware('can:account.people.create');

    Route::get('pessoas/{personId}/visualizar', [$controller, 'previewPerson'])
        ->whereNumber('personId')
        ->name('people.view')
        ->middleware('can:account.people.view');

    Route::get('pessoas/{personId}/editar', [$controller, 'personForm'])
        ->whereNumber('personId')
        ->name('people.edit')
        ->middleware('can:account.people.update');

    Route::post('pessoas', [$controller, 'savePerson'])
        ->name('people.store')
        ->middleware('can:account.people.create');

    Route::patch('pessoas/{personId}', [$controller, 'savePerson'])
        ->whereNumber('personId')
        ->name('people.update')
        ->middleware('can:account.people.update');

    Route::get('etiquetas', [$controller, 'tags'])
        ->name('tags.index')
        ->middleware('can:account.tags.list');

    Route::get('etiquetas/cadastrar', [$controller, 'tagForm'])
        ->name('tags.create')
        ->middleware('can:account.tags.create');

    Route::get('etiquetas/{tagId}/visualizar', [$controller, 'previewTag'])
        ->whereNumber('tagId')
        ->name('tags.view')
        ->middleware('can:account.tags.view');

    Route::get('etiquetas/{tagId}/editar', [$controller, 'tagForm'])
        ->whereNumber('tagId')
        ->name('tags.edit')
        ->middleware('can:account.tags.update');

    Route::post('etiquetas', [$controller, 'saveTag'])
        ->name('tags.store')
        ->middleware('can:account.tags.create');

    Route::delete('etiquetas/{tagId}', [$controller, 'deleteTag'])
        ->whereNumber('tagId')
        ->name('tags.destroy')
        ->middleware('can:account.tags.delete');

    Route::patch('etiquetas/{tagId}', [$controller, 'saveTag'])
        ->whereNumber('tagId')
        ->name('tags.update')
        ->middleware('can:account.tags.update');
});

Route::middleware(['auth', 'verified'])->prefix('conta')->name('account.access.')->group(function () {
    $controller = AccessController::class;

    Route::get('cargos', [$controller, 'roles'])
        ->middleware('can:account.roles.list')
        ->name('roles.index');

    Route::get('cargos/cadastrar', [$controller, 'roleForm'])
        ->middleware('can:account.roles.create')
        ->name('roles.create');

    Route::get('cargos/{roleId}/editar', [$controller, 'roleForm'])
        ->whereNumber('roleId')
        ->middleware('can:account.roles.update')
        ->name('roles.edit');

    Route::get('cargos/{roleId}/visualizar', [$controller, 'rolePreview'])
        ->whereNumber('roleId')
        ->middleware('can:account.roles.view')
        ->name('roles.view');

    Route::post('cargos', [$controller, 'saveRole'])
        ->middleware('can:account.roles.create')
        ->name('roles.store');

    Route::patch('cargos/{roleId}', [$controller, 'saveRole'])
        ->whereNumber('roleId')
        ->middleware('can:account.roles.update')
        ->name('roles.update');
});

Route::middleware(['auth', 'verified'])->prefix('conta')->name('account.companies.')->group(function () {
    Route::get('empresas', [CompanyController::class, 'index'])
        ->name('index')
        ->middleware('can:account.companies.list');

    Route::get('empresas/cadastrar', [CompanyController::class, 'form'])
        ->name('create')
        ->middleware('can:account.companies.create');

    Route::get('empresas/{companyId}/editar', [CompanyController::class, 'form'])
        ->whereNumber('companyId')
        ->name('edit')
        ->middleware('can:account.companies.update');

    Route::get('empresas/{companyId}/visualizar', [CompanyController::class, 'preview'])
        ->whereNumber('companyId')
        ->name('view')
        ->middleware('can:account.companies.view');

    Route::post('empresas', [CompanyController::class, 'save'])
        ->name('store')
        ->middleware('can:account.companies.create');

    Route::patch('empresas/{companyId}', [CompanyController::class, 'save'])
        ->whereNumber('companyId')
        ->name('update')
        ->middleware('can:account.companies.update');
});

Route::middleware(['auth', 'verified'])->prefix('conta')->name('account.establishments.')->group(function () {
    Route::get('estabelecimentos', [EstablishmentController::class, 'index'])
        ->name('index')
        ->middleware('can:account.establishments.list');

    Route::get('estabelecimentos/cadastrar', [EstablishmentController::class, 'form'])
        ->name('create')
        ->middleware('can:account.establishments.create');

    Route::get('estabelecimentos/{establishmentId}/editar', [EstablishmentController::class, 'form'])
        ->whereNumber('establishmentId')
        ->name('edit')
        ->middleware('can:account.establishments.update');

    Route::get('estabelecimentos/{establishmentId}/visualizar', [EstablishmentController::class, 'preview'])
        ->whereNumber('establishmentId')
        ->name('view')
        ->middleware('can:account.establishments.view');

    Route::post('estabelecimentos', [EstablishmentController::class, 'save'])
        ->name('store')
        ->middleware('can:account.establishments.create');

    Route::patch('estabelecimentos/{establishmentId}', [EstablishmentController::class, 'save'])
        ->whereNumber('establishmentId')
        ->name('update')
        ->middleware('can:account.establishments.update');

    Route::post('estabelecimentos/{establishmentId}/principal', [EstablishmentController::class, 'promote'])
        ->whereNumber('establishmentId')
        ->name('promote')
        ->middleware('can:account.establishments.promote');
});
