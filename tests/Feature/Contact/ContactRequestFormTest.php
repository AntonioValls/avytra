<?php

use App\Actions\Contact\SubmitContactRequest;
use App\Enums\ContactMethod;
use App\Models\ContactRequest;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\ContactRequestReceived;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function openContactForm(Listing $listing): Testable
{
    // The component can only be "opened" through mount; the Locked timestamp is set there.
    $component = Livewire::test('public.contact-request-form', ['listingId' => $listing->id]);
    test()->travel(10)->seconds();

    return $component;
}

test('a visitor sends a message from the listing page and the seller is notified', function () {
    Notification::fake();
    $listing = Listing::factory()->published()->create(['contact_email' => 'buzon@example.com']);

    openContactForm($listing)
        ->call('submit')
        ->assertHasErrors(['name', 'email', 'message']);

    openContactForm($listing)
        ->set('name', 'Pedro')
        ->set('email', 'pedro@example.com')
        ->set('phone', '+34600999888')
        ->set('message', 'Me interesa. ¿Podemos hablar?')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('message', '');

    $request = ContactRequest::query()->sole();

    expect($request->listing_id)->toBe($listing->id)
        ->and($request->sender_name)->toBe('Pedro')
        ->and($request->sender_user_id)->toBeNull();

    Notification::assertSentOnDemand(ContactRequestReceived::class);
});

test('a logged-in visitor gets name and email prefilled and is linked by account', function () {
    Notification::fake();
    $user = User::factory()->create(['name' => 'Lucía Compradora', 'email' => 'lucia@example.com']);
    $listing = Listing::factory()->published()->create();

    $this->actingAs($user);

    openContactForm($listing)
        ->assertSet('name', 'Lucía Compradora')
        ->assertSet('email', 'lucia@example.com')
        ->set('message', 'Hola, quiero más información sobre el negocio.')
        ->call('submit')
        ->assertHasNoErrors();

    expect(ContactRequest::query()->sole()->sender_user_id)->toBe($user->id);
});

test('bots that fill the honeypot or submit too fast are silently ignored', function () {
    Notification::fake();
    $listing = Listing::factory()->published()->create();

    Livewire::test('public.contact-request-form', ['listingId' => $listing->id])
        ->set('name', 'Bot')
        ->set('email', 'bot@example.com')
        ->set('message', 'Buy cheap stuff now')
        ->call('submit')
        ->assertHasNoErrors();

    openContactForm($listing)
        ->set('name', 'Bot')
        ->set('email', 'bot@example.com')
        ->set('message', 'Buy cheap stuff now')
        ->set('website', 'https://spam.example')
        ->call('submit')
        ->assertHasNoErrors();

    expect(ContactRequest::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

test('messages are rate limited per IP', function () {
    Notification::fake();
    $listing = Listing::factory()->published()->create();

    for ($i = 0; $i < (int) config('avytra.contact.request_rate_limit_per_hour'); $i++) {
        RateLimiter::hit('contact-request:127.0.0.1', 3600);
    }

    openContactForm($listing)
        ->set('name', 'Pedro')
        ->set('email', 'pedro@example.com')
        ->set('message', 'Me interesa.')
        ->call('submit')
        ->assertHasErrors(['message']);

    expect(ContactRequest::query()->count())->toBe(0);
});

test('a visitor cannot send more than the daily cap to the same listing', function () {
    Notification::fake();
    $listing = Listing::factory()->published()->create();
    ContactRequest::factory()->forListing($listing)
        ->count((int) config('avytra.contact.requests_per_listing_per_day'))
        ->create(['ip_hash' => SubmitContactRequest::hashIp('127.0.0.1')]);

    openContactForm($listing)
        ->set('name', 'Pedro')
        ->set('email', 'pedro@example.com')
        ->set('message', 'Otra vez yo.')
        ->call('submit')
        ->assertHasErrors(['message']);

    expect(ContactRequest::query()->count())->toBe((int) config('avytra.contact.requests_per_listing_per_day'));
});

test('only published listings accept messages', function (string $state) {
    $listing = Listing::factory()->{$state}()->create();

    openContactForm($listing)
        ->set('name', 'Pedro')
        ->set('email', 'pedro@example.com')
        ->set('message', 'Me interesa.')
        ->call('submit')
        ->assertNotFound();
})->with(['paused', 'sold', 'draft']);

test('the listing page offers "Send a message" instead of revealing the email, even without a contact email', function () {
    $withEmail = Listing::factory()->published()->create([
        'slug' => 'con-email',
        'preferred_contact_method' => ContactMethod::Email,
        'contact_email' => 'secreto@example.com',
    ]);
    $phoneOnly = Listing::factory()->published()->create([
        'slug' => 'solo-telefono',
        'preferred_contact_method' => ContactMethod::Phone,
        'contact_phone' => '+34600111222',
        'contact_email' => null,
    ]);

    $this->get(route('listings.show', $withEmail->slug))
        ->assertOk()
        ->assertSee(__('Send a message'))
        ->assertDontSee(__('Show email'))
        ->assertDontSee('secreto@example.com');

    $this->get(route('listings.show', $phoneOnly->slug))
        ->assertOk()
        ->assertSee(__('Show phone'))
        ->assertSee(__('Send a message'))
        ->assertDontSee($phoneOnly->owner()->email);

    Livewire::test('public.contact-box', ['listingId' => $withEmail->id])
        ->call('reveal')
        ->assertDontSee('secreto@example.com');
});
