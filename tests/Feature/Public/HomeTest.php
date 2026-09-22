<?php

use App\Models\Business;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;

test('the home page renders the brand claim, the search form and calls to action for guests', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(__('Find a business that is already up and running.'))
        ->assertSee(route('register'))
        ->assertSee(route('publish.landing'))
        ->assertSee('action="'.route('listings.index').'"', false)
        ->assertSee('og:image', false)
        ->assertSee('<link rel="canonical" href="'.route('home').'">', false)
        ->assertSee('"@type":"WebSite"', false);
});

test('the home page shows the latest visible listings and quick links to sectors with listings', function () {
    $sector = Category::factory()->create(['name' => 'Hostelería']);
    Listing::factory()->forBusiness(Business::factory()->create(['category_id' => $sector->id]))->published()->create(['title' => 'Bar publicado']);
    Listing::factory()->paused()->create(['title' => 'Pausada oculta']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Bar publicado')
        ->assertDontSee('Pausada oculta')
        ->assertSee(route('categories.show', $sector));
});

test('the home page shows an empty state when nothing is published yet', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(__('The first listings are on their way.'));
});

test('the home page points authenticated users to their panel', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertOk()
        ->assertSee(route('dashboard'));
});
