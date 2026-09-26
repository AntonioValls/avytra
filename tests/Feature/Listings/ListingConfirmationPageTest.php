<?php

use App\Enums\ListingEventType;
use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;
use App\Support\Listings\ConfirmationLink;
use Livewire\Livewire;

/**
 * @return array{expires: string, signature: string}
 */
function signedQuery(Listing $listing): array
{
    parse_str((string) parse_url(ConfirmationLink::for($listing), PHP_URL_QUERY), $query);

    return ['expires' => (string) $query['expires'], 'signature' => (string) $query['signature']];
}

test('a guest is sent to login and lands on the confirmation page afterwards', function () {
    $listing = Listing::factory()->published()->create();
    $url = ConfirmationLink::for($listing);

    $this->get($url)->assertRedirect(route('login'));

    $this->post(route('login'), ['email' => $listing->owner()->email, 'password' => 'password'])
        ->assertRedirect($url);

    $this->get($url)->assertOk()->assertSee(__('Yes, it is still available'));
});

test('somebody who is not the owner cannot open the page, the superadmin can', function () {
    $listing = Listing::factory()->published()->create();
    $url = ConfirmationLink::for($listing);

    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    $this->actingAs(User::factory()->superadmin()->create())->get($url)->assertOk();
});

test('the owner confirms a published listing from the email link and the event keeps the channel and actor', function () {
    $listing = Listing::factory()->needingConfirmation()->create(['first_reminder_sent_at' => now()->subDay()]);
    $owner = $listing->owner();

    Livewire::actingAs($owner)
        ->withQueryParams(signedQuery($listing))
        ->test('pages::listings.confirm', ['listing' => $listing])
        ->assertSee(__('Yes, it is still available'))
        ->call('confirm')
        ->assertSee(__('Thank you. Your listing stays available until :date.', ['date' => now()->addDays(config('avytra.freshness.confirmation_period_days'))->translatedFormat('j \d\e F')]));

    $listing->refresh();
    $event = $listing->events()->where('type', ListingEventType::Confirmed)->sole();

    expect($listing->needsConfirmation())->toBeFalse()
        ->and($listing->first_reminder_sent_at)->toBeNull()
        ->and($event->actor_user_id)->toBe($owner->id)
        ->and($event->payload['channel'])->toBe('email_link');
});

test('the same button reactivates a listing that was paused automatically', function () {
    $listing = Listing::factory()->expired()->create();

    Livewire::actingAs($listing->owner())
        ->withQueryParams(signedQuery($listing))
        ->test('pages::listings.confirm', ['listing' => $listing])
        ->assertSee(__('Paused on :date for lack of confirmation.', ['date' => $listing->expired_at->translatedFormat('j \d\e F')]))
        ->call('confirm')
        ->assertSee(__('We will remind you by email before that date. You can also confirm at any time from your panel.'));

    $listing->refresh();

    expect($listing->status)->toBe(ListingStatus::Published)
        ->and($listing->expired_at)->toBeNull()
        ->and($listing->events()->where('type', ListingEventType::Resumed)->sole()->payload['channel'])->toBe('email_link');
});

test('a listing paused by the owner offers to reactivate it and does nothing until asked', function () {
    $listing = Listing::factory()->paused()->create();

    $page = Livewire::actingAs($listing->owner())
        ->withQueryParams(signedQuery($listing))
        ->test('pages::listings.confirm', ['listing' => $listing])
        ->assertSee(__('It is paused by you.'))
        ->assertSee(__('Reactivate'));

    expect($listing->fresh()->status)->toBe(ListingStatus::Paused);

    $page->call('confirm');

    expect($listing->fresh()->status)->toBe(ListingStatus::Published);
});

test('an expired link shows the page without the action and points to the panel', function () {
    $listing = Listing::factory()->needingConfirmation()->create();
    $url = ConfirmationLink::for($listing);

    $this->travel(config('avytra.freshness.confirmation_link_ttl_days') + 1)->days();

    $this->actingAs($listing->owner())
        ->get($url)
        ->assertOk()
        ->assertSee(__('This link has expired.'))
        ->assertSee(route('panel.listings.index'))
        ->assertDontSee(__('Yes, it is still available'));

    Livewire::actingAs($listing->owner())
        ->test('pages::listings.confirm', ['listing' => $listing])
        ->call('confirm')
        ->assertForbidden();

    expect($listing->fresh()->needsConfirmation())->toBeTrue();
});

test('a sold listing shows its status and no button', function () {
    $listing = Listing::factory()->sold()->create();

    Livewire::actingAs($listing->owner())
        ->withQueryParams(signedQuery($listing))
        ->test('pages::listings.confirm', ['listing' => $listing])
        ->assertSee(__('Sold'))
        ->assertDontSee(__('Yes, it is still available'))
        ->call('confirm')
        ->assertSee(__('Sold'));

    expect($listing->fresh()->status)->toBe(ListingStatus::Sold);
});

test('the secondary actions mark the listing as sold or pause it and return to the panel', function () {
    $sold = Listing::factory()->published()->create();
    $paused = Listing::factory()->published()->create();

    Livewire::actingAs($sold->owner())
        ->withQueryParams(signedQuery($sold))
        ->test('pages::listings.confirm', ['listing' => $sold])
        ->call('markSold')
        ->assertRedirect(route('panel.listings.index'));

    Livewire::actingAs($paused->owner())
        ->withQueryParams(signedQuery($paused))
        ->test('pages::listings.confirm', ['listing' => $paused])
        ->call('pause')
        ->assertRedirect(route('panel.listings.index'));

    expect($sold->fresh()->status)->toBe(ListingStatus::Sold)
        ->and($paused->fresh()->status)->toBe(ListingStatus::Paused);
});
