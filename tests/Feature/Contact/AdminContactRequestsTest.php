<?php

use App\Models\ContactRequest;
use App\Models\Listing;

test('the admin listing detail lists the messages received, flagging undelivered ones', function () {
    actingAsSuperadmin();
    $listing = Listing::factory()->published()->create();
    ContactRequest::factory()->forListing($listing)->create(['sender_name' => 'Pedro Interesado']);
    ContactRequest::factory()->forListing($listing)->undelivered('Connection refused')->create(['sender_name' => 'Sin Entregar']);

    $this->get(route('admin.listings.show', $listing))
        ->assertOk()
        ->assertSee(__('Messages received'))
        ->assertSee('Pedro Interesado')
        ->assertSee('Sin Entregar')
        ->assertSee('Connection refused');
});

test('the operational summary counts messages whose email could not be delivered', function () {
    actingAsSuperadmin();
    ContactRequest::factory()->undelivered()->count(2)->create();
    ContactRequest::factory()->create();

    $this->get(route('admin.index'))
        ->assertOk()
        ->assertSee(__('Undelivered messages'))
        ->assertSeeInOrder([__('Undelivered messages'), '2']);
});
