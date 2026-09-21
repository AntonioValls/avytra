<?php

use App\Enums\UserRole;
use App\Models\User;

test('the command grants the superadmin role to an existing user', function () {
    $user = User::factory()->create();

    $this->artisan('avytra:superadmin', ['email' => $user->email])
        ->assertSuccessful();

    expect($user->fresh()->role)->toBe(UserRole::Superadmin)
        ->and($user->fresh()->isSuperadmin())->toBeTrue();
});

test('the command can revoke the superadmin role', function () {
    $user = User::factory()->superadmin()->create();

    $this->artisan('avytra:superadmin', ['email' => $user->email, '--revoke' => true])
        ->assertSuccessful();

    expect($user->fresh()->role)->toBe(UserRole::User);
});

test('the command fails for an unknown email', function () {
    $this->artisan('avytra:superadmin', ['email' => 'nobody@example.com'])
        ->assertFailed();
});

test('the role cannot be mass assigned', function () {
    $user = User::factory()->create();

    $user->fill(['role' => UserRole::Superadmin->value])->save();

    expect($user->fresh()->role)->toBe(UserRole::User);
});
