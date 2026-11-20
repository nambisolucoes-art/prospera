<?php

use App\Domains\Ajuda\UI\Controllers\HelpController;
use App\Domains\Auditoria\UI\Controllers\AuditController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/ajuda', [HelpController::class, 'index'])
        ->middleware('can:self.help.list')
        ->name('help.index');

    Route::get('/ajuda/{slug}', [HelpController::class, 'show'])
        ->where('slug', '[a-z0-9-]+')
        ->middleware('can:self.help.view')
        ->name('help.show');

    Route::get('/conta/auditoria', [AuditController::class, 'index'])
        ->middleware('can:account.audit.list')
        ->name('account.audit.index');

    Route::get('/conta/auditoria/{recordId}/visualizar', [AuditController::class, 'show'])
        ->whereNumber('recordId')
        ->middleware('can:account.audit.view')
        ->name('account.audit.show');

    Route::get('/saas/auditoria', [AuditController::class, 'index'])
        ->middleware(['can:access-saas', 'can:platform.audit.list'])
        ->name('platform.audit.index');

    Route::get('/saas/auditoria/{recordId}/visualizar', [AuditController::class, 'show'])
        ->whereNumber('recordId')
        ->middleware(['can:access-saas', 'can:platform.audit.view'])
        ->name('platform.audit.show');
});
