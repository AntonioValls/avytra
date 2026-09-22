<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'public.home')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('panel', 'pages::dashboard')->name('dashboard');

    Route::livewire('panel/empresas', 'pages::businesses.index')->name('businesses.index');
    Route::livewire('panel/empresas/crear', 'pages::businesses.form')->name('businesses.create');
    Route::livewire('panel/empresas/{business}/editar', 'pages::businesses.form')->name('businesses.edit');

    Route::livewire('panel/publicaciones', 'pages::listings.index')->name('listings.index');
    Route::livewire('panel/publicaciones/nueva', 'pages::listings.wizard')->name('listings.create');
    Route::livewire('panel/publicaciones/{listing}/editar', 'pages::listings.wizard')->name('listings.edit');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
