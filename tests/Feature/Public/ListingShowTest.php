<?php

use App\Enums\ContactMethod;
use App\Enums\Disclosure;
use App\Enums\FinancialMetric;
use App\Models\Business;
use App\Models\Listing;
use App\Models\ListingFinancialMetric;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

function publishedListingWithPrivateData(): Listing
{
    $owner = User::factory()->create(['name' => 'Propietaria Secreta', 'email' => 'cuenta-privada@example.com', 'phone' => '+34999888777']);

    $business = Business::factory()
        ->physical()
        ->ownedBy($owner)
        ->withLocation(Location::factory()->approximate()->withCoordinates(39.888888, -0.077777)->state([
            'address_line' => 'Calle Privada 99',
            'postal_code' => '12530',
            'public_latitude' => 39.89,
            'public_longitude' => -0.08,
            'public_radius_m' => 700,
        ]))
        ->create(['legal_name' => 'Razón Social Oculta, S.L.', 'show_legal_form' => false]);

    $listing = Listing::factory()->forBusiness($business)->published()->create([
        'title' => 'Traspaso de panadería en Borriana',
        'slug' => 'traspaso-de-panaderia-en-borriana',
        'preferred_contact_method' => ContactMethod::Phone,
        'contact_name' => 'Marta',
        'contact_phone' => '+34600111222',
        'contact_email' => 'contacto-publico@example.com',
    ]);

    ListingFinancialMetric::factory()->for($listing)->create(['metric' => FinancialMetric::Ebitda, 'disclosure' => Disclosure::Hidden, 'amount' => 38271]);

    return $listing;
}

test('a published listing renders its public data and a single JSON-LD block in the body', function () {
    $listing = publishedListingWithPrivateData();

    $response = $this->get(route('listings.show', $listing->slug))
        ->assertOk()
        ->assertSee('Traspaso de panadería en Borriana')
        ->assertSee('Marta')
        ->assertSee(__('Contact the owner'))
        ->assertSee('<link rel="canonical" href="'.route('listings.show', $listing->slug).'">', false)
        ->assertSee('"@type":"Offer"', false)
        ->assertSee('"@type":"BreadcrumbList"', false)
        ->assertDontSee('name="robots"', false);

    $html = $response->getContent();
    $body = substr($html, strpos($html, '<body'));

    expect(substr_count($body, '"@type":"Offer"'))->toBe(1)
        ->and(substr_count($html, '<head>'))->toBe(1);
});

test('the public page never leaks private data of the listing, the location or the account', function () {
    $listing = publishedListingWithPrivateData();

    $this->get(route('listings.show', $listing->slug))
        ->assertOk()
        ->assertDontSee('Razón Social Oculta')
        ->assertDontSee('Calle Privada 99')
        ->assertDontSee('12530')
        ->assertDontSee('39.888888')
        ->assertDontSee('-0.077777')
        ->assertDontSee('Propietaria Secreta')
        ->assertDontSee('cuenta-privada@example.com')
        ->assertDontSee('+34999888777')
        ->assertDontSee('38271')
        ->assertDontSee('38.271');
});

test('the phone is absent from the initial HTML and appears only after revealing; the email never does', function () {
    $listing = publishedListingWithPrivateData();

    $this->get(route('listings.show', $listing->slug))
        ->assertOk()
        ->assertDontSee('+34600111222')
        ->assertDontSee('contacto-publico@example.com')
        ->assertSee(__('Show phone'))
        ->assertSee(__('Send a message'));

    Livewire::test('public.contact-box', ['listingId' => $listing->id])
        ->assertDontSee('+34600111222')
        ->call('reveal')
        ->assertSet('revealed', true)
        ->assertSee('+34600111222')
        ->assertDontSee('contacto-publico@example.com');
});

test('revealing contact details is rate limited per IP', function () {
    $listing = publishedListingWithPrivateData();

    $key = 'contact-reveal:'.request()->ip();
    $max = (int) config('avytra.contact.reveal_rate_limit_per_hour');

    for ($i = 0; $i < $max; $i++) {
        RateLimiter::hit($key, 3600);
    }

    Livewire::test('public.contact-box', ['listingId' => $listing->id])
        ->call('reveal')
        ->assertSet('revealed', false)
        ->assertDontSee('+34600111222');
});

test('a listing that is not public answers 404 to visitors and 200 with a banner to its owner', function (string $state) {
    $listing = Listing::factory()->{$state}()->create(['slug' => "privada-{$state}"]);

    $this->get(route('listings.show', $listing->slug))->assertNotFound();

    $this->actingAs(User::factory()->create())->get(route('listings.show', $listing->slug))->assertNotFound();

    $this->actingAs($listing->owner())
        ->get(route('listings.show', $listing->slug))
        ->assertOk()
        ->assertSee(__('This listing is not public (:status).', ['status' => $listing->status->label()]))
        ->assertSee('noindex', false);

    auth()->logout();

    $this->actingAs(User::factory()->superadmin()->create())->get(route('listings.show', $listing->slug))->assertOk();
})->with(['draft', 'paused', 'expired', 'suspended']);

test('archived and deleted listings answer 410 Gone', function () {
    $archived = Listing::factory()->archived()->create(['slug' => 'archivada']);
    $deleted = Listing::factory()->published()->create(['slug' => 'borrada']);
    $deleted->delete();

    $this->get(route('listings.show', 'archivada'))->assertStatus(410)->assertSee(__('This listing is no longer available.'));
    $this->get(route('listings.show', 'borrada'))->assertStatus(410);
    $this->get(route('listings.show', 'no-existe'))->assertNotFound()->assertSee(__('We could not find that page.'));
});

test('an old slug redirects permanently to the current one', function () {
    $listing = Listing::factory()->published()->create(['slug' => 'nuevo-slug']);
    $listing->slugRedirects()->create(['old_slug' => 'viejo-slug']);

    $this->get(route('listings.show', 'viejo-slug'))
        ->assertRedirect(route('listings.show', 'nuevo-slug'))
        ->assertStatus(301);
});

test('a recently sold listing is public without contact and an old one is noindex', function () {
    $recent = Listing::factory()->sold()->create(['slug' => 'vendida-reciente', 'contact_phone' => '+34600000000']);
    $old = Listing::factory()->sold(now()->subDays(config('avytra.freshness.sold_visible_days') + 1))->create(['slug' => 'vendida-antigua']);

    $this->get(route('listings.show', 'vendida-reciente'))
        ->assertOk()
        ->assertSee(__('This business has already changed hands.'))
        ->assertDontSee(__('Contact the owner'))
        ->assertDontSee('name="robots"', false);

    $this->get(route('listings.show', 'vendida-antigua'))
        ->assertOk()
        ->assertSee('noindex', false);
});

test('the contact box refuses to reveal details of a listing that is not public', function () {
    $listing = Listing::factory()->paused()->create(['contact_phone' => '+34600111222']);

    Livewire::test('public.contact-box', ['listingId' => $listing->id])
        ->call('reveal')
        ->assertNotFound();
});
