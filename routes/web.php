<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? to_route('dashboard') : to_route('login'))->name('home');

// Inscription fermée : on remplace les routes de Fortify par des 404 (mêmes noms, mêmes URL)
if (! config('merimap.registration')) {
    Route::get('register', fn () => abort(404))->middleware('guest')->name('register');
    Route::post('register', fn () => abort(404))->middleware('guest')->name('register.store');
}

require __DIR__.'/cmdb.php';
require __DIR__.'/settings.php';
