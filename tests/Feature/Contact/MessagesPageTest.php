<?php

use App\Models\Business;
use App\Models\ContactRequest;
use App\Models\Listing;
use App\Models\User;
use Livewire\Livewire;

test('the messages page lists only the messages of the listings the user owns, unread first', function () {
    $owner = User::factory()->create();
    $listing = Listing::factory()->forBusiness(Business::factory()->ownedBy($owner)->create())->published()->create(['title' => 'Traspaso de cafetería en Vinaròs']);
    $mine = ContactRequest::factory()->forListing($listing)->create(['sender_name' => 'Pedro Interesado', 'message' => 'Quiero visitar el local.']);
    ContactRequest::factory()->create(['sender_name' => 'Ajeno Curioso']);

    $this->actingAs($owner)
        ->get(route('panel.messages.index'))
        ->assertOk()
        ->assertSee('Pedro Interesado')
        ->assertSee('Traspaso de cafetería en Vinaròs')
        ->assertDontSee('Ajeno Curioso');

    expect($mine->fresh()->isRead())->toBeFalse();
});

test('guests are redirected and a user without messages sees the empty state', function () {
    $this->get(route('panel.messages.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('panel.messages.index'))
        ->assertOk()
        ->assertSee(__('You have no messages yet.'));
});

test('opening a message marks it as read and offers a reply link to the sender', function () {
    $owner = User::factory()->create();
    $listing = Listing::factory()->forBusiness(Business::factory()->ownedBy($owner)->create())->published()->create();
    $request = ContactRequest::factory()->forListing($listing)->create(['sender_email' => 'pedro@example.com', 'message' => 'Quiero visitar el local.']);

    $this->actingAs($owner);

    Livewire::test('pages::messages.index')
        ->call('open', $request->id)
        ->assertSet('openId', $request->id)
        ->assertSee('Quiero visitar el local.')
        ->assertSeeHtml('href="mailto:pedro@example.com');

    expect($request->fresh()->isRead())->toBeTrue();
});

test('a user cannot open a message of another owner', function () {
    $request = ContactRequest::factory()->create();

    $this->actingAs(User::factory()->create());

    Livewire::test('pages::messages.index')
        ->call('open', $request->id)
        ->assertForbidden();

    expect($request->fresh()->isRead())->toBeFalse();
});

test('the sidebar and the panel home show how many messages are unread', function () {
    $owner = User::factory()->create();
    $listing = Listing::factory()->forBusiness(Business::factory()->ownedBy($owner)->create())->published()->create();
    ContactRequest::factory()->forListing($listing)->count(2)->create();
    ContactRequest::factory()->forListing($listing)->read()->create();

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(trans_choice('You have one unread message.|You have :count unread messages.', 2, ['count' => 2]))
        ->assertSee(__('Messages'));

    expect($owner->unreadContactRequestsCount())->toBe(2);
});

test('undelivered messages are flagged so the owner knows the email did not arrive', function () {
    $owner = User::factory()->create();
    $listing = Listing::factory()->forBusiness(Business::factory()->ownedBy($owner)->create())->published()->create();
    ContactRequest::factory()->forListing($listing)->undelivered()->create(['sender_name' => 'Sin Entregar']);

    $this->actingAs($owner)
        ->get(route('panel.messages.index'))
        ->assertOk()
        ->assertSee('Sin Entregar')
        ->assertSee(__('The email could not be delivered'));
});
