<?php

use App\Domains\Usuarios\UI\Controllers\Settings\AccessEmailController;
use App\Domains\Usuarios\UI\Controllers\Settings\ProfileController;
use App\Domains\Usuarios\UI\Controllers\Settings\ProfilePhotoController;
use App\Domains\Usuarios\UI\Controllers\Settings\SecurityController;
use App\Support\ApiPage;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit')
        ->middleware('can:self.profile.view');

    Route::patch('settings/profile', [ProfileController::class, 'update'])
        ->name('profile.update')
        ->middleware('can:self.profile.update');

    Route::post('settings/profile/email', [AccessEmailController::class, 'request'])
        ->name('profile.email.request')
        ->middleware(['can:self.profile.update', 'throttle:5,1']);

    Route::get('settings/profile/email/confirm', [AccessEmailController::class, 'confirm'])
        ->name('profile.email.confirm')
        ->middleware(['can:self.profile.update', 'throttle:6,1', 'signed']);

    Route::post('settings/profile/photo', [ProfilePhotoController::class, 'store'])
        ->name('profile.photo.store')
        ->middleware('can:self.profile.photo');

    Route::get('settings/profile/photo/{version}', [ProfilePhotoController::class, 'show'])
        ->whereUuid('version')
        ->name('profile.photo.show');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy')
        ->middleware('can:self.profile.delete');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit')
        ->middleware('can:self.security.view');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update')
        ->middleware('can:self.security.password');

    Route::get('settings/appearance', fn () => ApiPage::respond())
        ->name('appearance.edit')
        ->middleware('can:self.appearance.view');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
