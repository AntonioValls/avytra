<?php

use App\Enums\ListingStatus;
use App\Models\Business;
use App\Models\Listing;
use App\Models\User;
use Livewire\Livewire;

test('the list shows only the listings of the businesses the user owns', function () {
    $user = User::factory()->create();
    $mine = Listing::factory()->forBusiness(Business::factory()->ownedBy($user)->create())->published()->create(['title' => 'Mi traspaso']);
    $foreign = Listing::factory()->published()->create(['title' => 'Traspaso ajeno']);

    $this->actingAs($user)
        ->get(route('listings.index'))
        ->assertOk()
        ->assertSee('Mi traspaso')
        ->assertDontSee('Traspaso ajeno')
        ->assertSee(__('Published'));
});

test('a user without listings sees an empty state that leads to the wizard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('listings.index'))
        ->assertOk()
        ->assertSee(__('You do not have any listings yet.'))
        ->assertSee(route('listings.create'));
});

test('the owner confirms availability, pauses and resumes from the list', function () {
    $listing = Listing::factory()->needingConfirmation()->create();
    actingAsOwnerOf($listing->business);

    $component = Livewire::test('pages::listings.index')
        ->assertSee(__('Needs confirmation'))
        ->call('confirmAvailability', $listing->id)
        ->assertHasNoErrors();

    expect($listing->fresh()->needsConfirmation())->toBeFalse();

    $component->call('pause', $listing->id);
    expect($listing->fresh()->status)->toBe(ListingStatus::Paused);

    $component->call('resume', $listing->id);
    expect($listing->fresh()->status)->toBe(ListingStatus::Published);
});

test('archiving, marking as sold and deleting a draft go through a confirmation modal', function () {
    $published = Listing::factory()->published()->create();
    $business = $published->business;
    actingAsOwnerOf($business);

    Livewire::test('pages::listings.index')
        ->call('openConfirmation', 'sold', $published->id)
        ->assertSet('pendingAction', 'sold')
        ->assertSee(__('Mark this listing as sold?'))
        ->call('runPendingAction')
        ->assertSet('pendingAction', '');

    expect($published->fresh()->status)->toBe(ListingStatus::Sold);

    $draft = Listing::factory()->forBusiness($business)->draft()->create();

    Livewire::test('pages::listings.index')
        ->call('openConfirmation', 'delete', $draft->id)
        ->call('runPendingAction');

    expect(Listing::withTrashed()->find($draft->id))->toBeNull();

    Livewire::test('pages::listings.index')
        ->call('openConfirmation', 'archive', $published->id)
        ->call('runPendingAction');

    expect($published->fresh()->status)->toBe(ListingStatus::Archived);
});

test('an invalid transition is reported instead of crashing', function () {
    $listing = Listing::factory()->draft()->create();
    actingAsOwnerOf($listing->business);

    Livewire::test('pages::listings.index')
        ->call('pause', $listing->id)
        ->assertHasNoErrors();

    expect($listing->fresh()->status)->toBe(ListingStatus::Draft);
});

test('publishing again a sold listing creates a new draft and opens the wizard', function () {
    $sold = Listing::factory()->sold()->create();
    actingAsOwnerOf($sold->business);

    $component = Livewire::test('pages::listings.index')->call('republish', $sold->id);

    $draft = Listing::where('status', ListingStatus::Draft)->sole();

    $component->assertRedirect(route('listings.edit', $draft));

    expect($draft->business_id)->toBe($sold->business_id);
});

test('another user cannot act on a listing they do not own', function () {
    $listing = Listing::factory()->published()->create();
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::listings.index')->call('pause', $listing->id)->assertForbidden();
    Livewire::test('pages::listings.index')->call('openConfirmation', 'archive', $listing->id)->assertForbidden();

    expect($listing->fresh()->status)->toBe(ListingStatus::Published);
});

test('the panel home shows actionable notices and lets the owner act on them', function () {
    $user = User::factory()->create();
    $business = Business::factory()->ownedBy($user)->create();
    $needing = Listing::factory()->forBusiness($business)->needingConfirmation()->create(['title' => 'Necesita confirmar']);
    $expired = Listing::factory()->forBusiness(Business::factory()->ownedBy($user)->create())->expired()->create(['title' => 'Caducada']);
    $draft = Listing::factory()->forBusiness(Business::factory()->ownedBy($user)->create(['name' => 'Empresa borrador']))->draft()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('“:title” needs confirmation.', ['title' => 'Necesita confirmar']))
        ->assertSee(__('“:title” was paused for lack of confirmation.', ['title' => 'Caducada']))
        ->assertSee(__('You have an unfinished draft for “:business”.', ['business' => 'Empresa borrador']))
        ->assertSee(route('listings.edit', $draft));

    Livewire::test('pages::dashboard')
        ->call('confirmAvailability', $needing->id)
        ->call('resume', $expired->id);

    expect($needing->fresh()->needsConfirmation())->toBeFalse()
        ->and($expired->fresh()->status)->toBe(ListingStatus::Published);
});
