<?php

use App\Models\User;

test('the home page renders the brand claim and calls to action for guests', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(__('Find a business that is already up and running.'))
        ->assertSee(route('register'))
        ->assertSee('og:image', false)
        ->assertSee('<link rel="canonical"', false);
});

test('the home page points authenticated users to their panel', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertOk()
        ->assertSee(route('dashboard'));
});
