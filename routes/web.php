<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');
Route::get('/api/localidades/municipios', fn () => response()->file(
    resource_path('data/municipios-ibge.json'),
    ['Content-Type' => 'application/json', 'Cache-Control' => 'public, max-age=86400'],
));

require __DIR__.'/settings.php';
require __DIR__.'/saas.php';
require __DIR__.'/account.php';

require __DIR__.'/guidance.php';
