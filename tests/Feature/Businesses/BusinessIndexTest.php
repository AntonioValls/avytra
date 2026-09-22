<?php

use App\Models\Business;
use App\Models\User;

test('the list shows only the businesses the user owns', function () {
    $user = User::factory()->create();
    $mine = Business::factory()->ownedBy($user)->withLocation()->create(['name' => 'Mi panadería']);
    $createdForMeByAdmin = Business::factory()->ownedBy($user)->createdBy(User::factory()->superadmin()->create())->online()->create(['name' => 'Mi tienda online']);
    $foreign = Business::factory()->create(['name' => 'Negocio ajeno']);

    $this->actingAs($user)
        ->get(route('businesses.index'))
        ->assertOk()
        ->assertSee($mine->name)
        ->assertSee($createdForMeByAdmin->name)
        ->assertSee($mine->location->province->name)
        ->assertDontSee($foreign->name)
        ->assertSee(route('businesses.edit', $mine));
});

test('a user without businesses sees an empty state with a call to create one', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('businesses.index'))
        ->assertOk()
        ->assertSee(__('You do not have any businesses yet.'))
        ->assertSee(route('businesses.create'));
});

test('the panel home lists the businesses and links to the full list', function () {
    $user = User::factory()->create();
    $business = Business::factory()->ownedBy($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee($business->name)
        ->assertSee(route('businesses.index'));
});
