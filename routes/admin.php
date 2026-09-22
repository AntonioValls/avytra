<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Administration routes
|--------------------------------------------------------------------------
|
| Only the superadmin can reach these. The middleware answers 404 to anybody
| else so the existence of the area is not revealed.
|
*/

Route::middleware(['auth', 'verified', 'superadmin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::view('/', 'admin.index')->name('index');

        Route::livewire('empresas', 'pages::admin.businesses.index')->name('businesses.index');
        // The shared form receives admin=true through its mount() and shows the owner selector.
        Route::livewire('empresas/crear', 'pages::businesses.form')->defaults('admin', true)->name('businesses.create');
        Route::livewire('empresas/{business}/editar', 'pages::businesses.form')->defaults('admin', true)->name('businesses.edit');
    });
