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
        Route::livewire('/', 'pages::admin.index')->name('index');

        Route::livewire('empresas', 'pages::admin.businesses.index')->name('businesses.index');
        // The shared form receives admin=true through its mount() and shows the owner selector.
        Route::livewire('empresas/crear', 'pages::businesses.form')->defaults('admin', true)->name('businesses.create');
        Route::livewire('empresas/{business}/editar', 'pages::businesses.form')->defaults('admin', true)->name('businesses.edit');

        Route::livewire('publicaciones', 'pages::admin.listings.index')->name('listings.index');
        // The same wizard as the panel, with an owner selector in step 1.
        Route::livewire('publicaciones/nueva', 'pages::listings.wizard')->defaults('admin', true)->name('listings.create');
        Route::livewire('publicaciones/{listing}', 'pages::admin.listings.show')->name('listings.show');
        Route::livewire('publicaciones/{listing}/editar', 'pages::listings.wizard')->defaults('admin', true)->name('listings.edit');

        Route::livewire('reportes', 'pages::admin.reports.index')->name('reports.index');
    });
