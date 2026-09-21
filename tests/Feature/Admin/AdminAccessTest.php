<?php

use App\Models\User;

test('guests are redirected to login when visiting the admin area', function () {
    $this->get(route('admin.index'))->assertRedirect(route('login'));
});

test('regular users receive a 404 for the admin area so it is not revealed', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.index'))
        ->assertNotFound();
});

test('superadmins can open the admin area', function () {
    $this->actingAs(User::factory()->superadmin()->create())
        ->get(route('admin.index'))
        ->assertOk()
        ->assertSee(__('Operational summary'));
});

test('the panel only shows the administration link to superadmins', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('admin.index'));

    $this->actingAs(User::factory()->superadmin()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('admin.index'));
});
