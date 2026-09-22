<?php

use App\Models\User;

test('the static public pages render with their canonical URL', function (string $routeName, string $text) {
    $this->get(route($routeName))
        ->assertOk()
        ->assertSee($text)
        ->assertSee('<link rel="canonical" href="'.route($routeName).'">', false);
})->with([
    'publish landing' => ['publish.landing', 'Vende o traspasa tu empresa gratis'],
    'how it works' => ['how-it-works', 'Cómo funciona'],
    'legal notice' => ['legal.notice', 'Aviso legal'],
    'privacy' => ['legal.privacy', 'Política de privacidad'],
    'cookies' => ['legal.cookies', 'Política de cookies'],
]);

test('the publish landing sends guests to register and users to the wizard', function () {
    $this->get(route('publish.landing'))->assertSee(route('register'));

    $this->actingAs(User::factory()->create())
        ->get(route('publish.landing'))
        ->assertSee(route('panel.listings.create'));
});

test('the publish landing shows the support contact from the configuration', function () {
    config(['avytra.support.phone' => '+34964000000', 'avytra.support.email' => 'hola@avytra.test']);

    $this->get(route('publish.landing'))
        ->assertSee('+34964000000')
        ->assertSee('hola@avytra.test');
});
