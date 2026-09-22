<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ListingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public marketplace (docs/08-public-pages-and-flows.md)
|--------------------------------------------------------------------------
|
| Public URLs are in Spanish. Every page renders through PublicListingPresenter;
| the "public" limiter caps anonymous browsing per IP.
|
*/

Route::middleware('throttle:public')->group(function () {
    Route::get('/', HomeController::class)->name('home');

    Route::livewire('empresas', 'pages::public.listings.index')->name('listings.index');
    Route::livewire('empresas/categoria/{category:slug}', 'pages::public.listings.index')->name('categories.show');
    Route::livewire('empresas/provincia/{province:slug}', 'pages::public.listings.index')->name('provinces.show');
    Route::livewire('negocios-online', 'pages::public.listings.index')->defaults('online', true)->name('listings.online');
    Route::get('empresas/{slug}', [ListingController::class, 'show'])->name('listings.show');

    Route::view('publicar', 'public.publish')->name('publish.landing');
    Route::view('como-funciona', 'public.how-it-works')->name('how-it-works');
    Route::view('aviso-legal', 'public.legal.notice')->name('legal.notice');
    Route::view('privacidad', 'public.legal.privacy')->name('legal.privacy');
    Route::view('cookies', 'public.legal.cookies')->name('legal.cookies');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('panel', 'pages::dashboard')->name('dashboard');

    Route::livewire('panel/empresas', 'pages::businesses.index')->name('businesses.index');
    Route::livewire('panel/empresas/crear', 'pages::businesses.form')->name('businesses.create');
    Route::livewire('panel/empresas/{business}/editar', 'pages::businesses.form')->name('businesses.edit');

    // "panel." prefix: the public explore page owns listings.index (docs/08).
    Route::livewire('panel/publicaciones', 'pages::listings.index')->name('panel.listings.index');
    Route::livewire('panel/publicaciones/nueva', 'pages::listings.wizard')->name('panel.listings.create');
    Route::livewire('panel/publicaciones/{listing}/editar', 'pages::listings.wizard')->name('panel.listings.edit');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
