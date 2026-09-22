<?php

use App\Enums\ListingStatus;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\ListingSuspended;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('regular users receive a 404 for the admin listing pages', function () {
    $listing = Listing::factory()->create();

    $this->actingAs($listing->owner())->get(route('admin.listings.index'))->assertNotFound();
    $this->actingAs($listing->owner())->get(route('admin.listings.show', $listing))->assertNotFound();
});

test('the superadmin sees every listing with its owner and can filter by status and condition', function () {
    $published = Listing::factory()->published()->create(['title' => 'Publicada reciente']);
    $needing = Listing::factory()->needingConfirmation()->create(['title' => 'Pendiente de confirmar']);
    $draft = Listing::factory()->draft()->create(['title' => 'Borrador ajeno']);

    actingAsSuperadmin();

    $this->get(route('admin.listings.index'))
        ->assertOk()
        ->assertSee('Publicada reciente')
        ->assertSee('Borrador ajeno')
        ->assertSee($draft->owner()->email);

    Livewire::test('pages::admin.listings.index')
        ->set('status', ListingStatus::Draft->value)
        ->assertSee('Borrador ajeno')
        ->assertDontSee('Publicada reciente')
        ->set('status', '')
        ->set('condition', 'needs_confirmation')
        ->assertSee('Pendiente de confirmar')
        ->assertDontSee('Publicada reciente')
        ->set('condition', '')
        ->set('search', $published->business->owner->email)
        ->assertSee('Publicada reciente')
        ->assertDontSee('Borrador ajeno');
});

test('the superadmin suspends a listing with a reason and lifts the suspension later', function () {
    Notification::fake();
    $admin = actingAsSuperadmin();
    $listing = Listing::factory()->published()->create();

    Livewire::test('pages::admin.listings.show', ['listing' => $listing])
        ->call('suspend')
        ->assertHasErrors(['suspensionReason'])
        ->set('suspensionReason', 'Información falsa sobre la facturación')
        ->call('suspend')
        ->assertHasNoErrors()
        ->assertSee('Información falsa sobre la facturación');

    expect($listing->fresh()->status)->toBe(ListingStatus::Suspended)
        ->and(AuditLog::where('action', 'listing.suspended')->where('actor_user_id', $admin->id)->exists())->toBeTrue();

    Notification::assertSentTo($listing->owner(), ListingSuspended::class);

    Livewire::test('pages::admin.listings.show', ['listing' => $listing->fresh()])
        ->call('unsuspend');

    expect($listing->fresh()->status)->toBe(ListingStatus::Published)
        ->and($listing->fresh()->suspension_reason)->toBeNull();
});

test('the superadmin confirms availability on behalf of the owner and it is traceable', function () {
    $admin = actingAsSuperadmin();
    $listing = Listing::factory()->needingConfirmation()->create();

    Livewire::test('pages::admin.listings.show', ['listing' => $listing])
        ->call('confirmOnBehalf');

    $event = $listing->events()->sole();

    expect($listing->fresh()->needsConfirmation())->toBeFalse()
        ->and($event->actor_user_id)->toBe($admin->id)
        ->and($event->on_behalf_of_user_id)->toBe($listing->owner()->id)
        ->and($event->payload)->toBe(['channel' => 'admin'])
        ->and(AuditLog::where('action', 'listing.confirmed_by_admin')->exists())->toBeTrue();
});

test('the detail page shows the history of events and lets the superadmin change the URL', function () {
    actingAsSuperadmin();
    $listing = Listing::factory()->published()->create(['slug' => 'traspaso-original']);
    $listing->events()->create(['type' => 'published']);

    Livewire::test('pages::admin.listings.show', ['listing' => $listing])
        ->assertSee(__('Published'))
        ->set('newSlug', 'traspaso nuevo')
        ->call('changeSlug')
        ->assertHasErrors(['newSlug'])
        ->set('newSlug', 'traspaso-nuevo')
        ->call('changeSlug')
        ->assertHasNoErrors()
        ->assertSee('traspaso-original');

    expect($listing->fresh()->slug)->toBe('traspaso-nuevo')
        ->and($listing->slugRedirects()->sole()->old_slug)->toBe('traspaso-original');
});

test('the superadmin publishes, pauses, resumes, marks as sold and archives from the detail page', function () {
    actingAsSuperadmin();
    $draft = Listing::factory()->forBusiness(Business::factory()->physical()->withLocation()->create(['description' => str_repeat('Negocio consolidado. ', 12)]))->create();

    $page = Livewire::test('pages::admin.listings.show', ['listing' => $draft]);

    $page->call('publish');
    expect($draft->fresh()->status)->toBe(ListingStatus::Published);

    $page->call('pause');
    expect($draft->fresh()->status)->toBe(ListingStatus::Paused);

    $page->call('resume');
    expect($draft->fresh()->status)->toBe(ListingStatus::Published);

    $page->call('markSold');
    expect($draft->fresh()->status)->toBe(ListingStatus::Sold);

    $page->call('archive');
    expect($draft->fresh()->status)->toBe(ListingStatus::Archived);

    expect(AuditLog::where('on_behalf_of_user_id', $draft->owner()->id)->count())->toBeGreaterThanOrEqual(5);
});

test('publishing an incomplete draft from the admin shows the missing data', function () {
    actingAsSuperadmin();
    $draft = Listing::factory()->bare()->create();

    Livewire::test('pages::admin.listings.show', ['listing' => $draft])
        ->call('publish')
        ->assertNotSet('publishErrors', [])
        ->assertSee(__('The listing cannot be published yet.'));

    expect($draft->fresh()->status)->toBe(ListingStatus::Draft);
});

test('a regular user cannot act through the admin detail component even if they own the listing', function () {
    $listing = Listing::factory()->published()->create();
    $this->actingAs($listing->owner());

    Livewire::test('pages::admin.listings.show', ['listing' => $listing])
        ->set('suspensionReason', 'Intento')
        ->call('suspend')
        ->assertForbidden();

    expect($listing->fresh()->status)->toBe(ListingStatus::Published);
});

test('a user who is not the owner nor superadmin cannot even open the admin detail component', function () {
    $listing = Listing::factory()->published()->create();
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::admin.listings.show', ['listing' => $listing])->assertForbidden();
});
