<?php

use App\Models\Business;
use App\Models\Listing;
use App\Models\Location;
use Database\Factories\LocationFactory;
use Livewire\Livewire;

function publishedListingWith(LocationFactory $location, string $title = 'Negocio con mapa'): Listing
{
    $business = Business::factory()->physical()->withLocation($location)->create();

    return Listing::factory()->forBusiness($business)->published()->create(['title' => $title]);
}

test('an exact location renders a pin on the public point with the address as label', function () {
    $listing = publishedListingWith(Location::factory()->exact()->withCoordinates(39.888888, -0.077777)->state([
        'address_line' => 'Calle Mayor 5',
        'public_latitude' => 39.888888,
        'public_longitude' => -0.077777,
        'public_radius_m' => null,
    ]));

    $this->get(route('listings.show', $listing->slug))
        ->assertOk()
        ->assertSee('data-map="listing"', false)
        ->assertSee('data-lat="39.888888"', false)
        ->assertSee('data-lng="-0.077777"', false)
        ->assertSee('data-radius=""', false)
        ->assertSee('data-label="Calle Mayor 5"', false)
        ->assertSee('https://www.openstreetmap.org/?mlat=39.888888&amp;mlon=-0.077777', false);
});

test('an approximate location renders a circle around the derived point and never the private coordinates', function () {
    $listing = publishedListingWith(Location::factory()->approximate()->withCoordinates(39.888888, -0.077777)->state([
        'address_line' => 'Calle Privada 99',
        'public_latitude' => 39.89,
        'public_longitude' => -0.08,
        'public_radius_m' => 700,
    ]));

    $this->get(route('listings.show', $listing->slug))
        ->assertOk()
        ->assertSee('data-map="listing"', false)
        ->assertSee('data-lat="39.89"', false)
        ->assertSee('data-lng="-0.08"', false)
        ->assertSee('data-radius="700"', false)
        ->assertSee('https://www.openstreetmap.org/#map=', false)
        ->assertDontSee('39.888888')
        ->assertDontSee('-0.077777')
        ->assertDontSee('Calle Privada 99')
        ->assertDontSee('mlat=', false);
});

test('a municipality-only location renders a wide circle over the municipality centre', function () {
    $listing = publishedListingWith(Location::factory()->cityOnly()->state([
        'address_line' => 'Calle Privada 99',
        'public_latitude' => 39.98,
        'public_longitude' => -0.05,
        'public_radius_m' => 1500,
    ]));

    $this->get(route('listings.show', $listing->slug))
        ->assertOk()
        ->assertSee('data-map="listing"', false)
        ->assertSee('data-lat="39.98"', false)
        ->assertSee('data-radius="1500"', false)
        ->assertDontSee('Calle Privada 99');
});

test('a hidden location renders no map at all', function () {
    $listing = publishedListingWith(Location::factory()->hidden()->withCoordinates(39.888888, -0.077777));

    $this->get(route('listings.show', $listing->slug))
        ->assertOk()
        ->assertSee(__('Location'))
        ->assertDontSee('data-map=', false)
        ->assertDontSee('39.888888');
});

test('the explore map is off by default and, once toggled, receives only the public points of the current page', function () {
    publishedListingWith(Location::factory()->exact()->withCoordinates(39.888888, -0.077777)->state([
        'public_latitude' => 39.888888,
        'public_longitude' => -0.077777,
        'public_radius_m' => null,
    ]), 'Con pin exacto');
    publishedListingWith(Location::factory()->approximate()->withCoordinates(40.111111, -0.222222)->state([
        'public_latitude' => 40.11,
        'public_longitude' => -0.22,
        'public_radius_m' => 700,
    ]), 'Con zona aproximada');
    publishedListingWith(Location::factory()->hidden()->withCoordinates(41.333333, -0.444444), 'Oculta');
    Listing::factory()->forBusiness(Business::factory()->online()->create())->published()->create(['title' => 'Online sin mapa']);
    Listing::factory()->forBusiness(Business::factory()->physical()->withLocation(Location::factory()->exact()->withCoordinates(42.5, -1.5)->state([
        'public_latitude' => 42.5,
        'public_longitude' => -1.5,
        'public_radius_m' => null,
    ]))->create())->paused()->create(['title' => 'Pausada fuera del mapa']);

    $component = Livewire::test('pages::public.listings.index')
        ->assertSee(__('Show map'))
        ->assertDontSee('data-map="explore"', false)
        ->call('toggleMap')
        ->assertSet('showMap', true)
        ->assertSee(__('Hide map'))
        ->assertSee('data-map="explore"', false)
        ->assertDontSee('41.333333')
        ->assertDontSee('40.111111')
        ->assertDontSee('42.5,');

    preg_match('/data-points="([^"]*)"/', $component->html(), $matches);
    $points = json_decode(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5), true);

    expect($points)->toHaveCount(2)
        ->and(collect($points)->pluck('title')->all())->toEqualCanonicalizing(['Con pin exacto', 'Con zona aproximada'])
        ->and(collect($points)->firstWhere('title', 'Con zona aproximada'))->toMatchArray(['lat' => 40.11, 'lng' => -0.22, 'radius' => 700])
        ->and(collect($points)->firstWhere('title', 'Con pin exacto')['radius'])->toBeNull()
        ->and(collect($points)->firstWhere('title', 'Con pin exacto')['url'])->toContain('/empresas/');

    $component->call('toggleMap')
        ->assertSet('showMap', false)
        ->assertDontSee('data-map="explore"', false);
});

test('the explore map explains itself when no listing on the page has a public location', function () {
    Listing::factory()->forBusiness(Business::factory()->online()->create())->published()->create(['title' => 'Solo online']);

    Livewire::test('pages::public.listings.index')
        ->call('toggleMap')
        ->assertSee('data-points="[]"', false)
        ->assertSee(__('No listing on this page has a public location.'));
});
