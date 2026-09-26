<?php

use App\Models\User;

test('a user may only view and update their own account', function (string $ability) {
    $user = User::factory()->create();
    $other = User::factory()->create();

    expect($user->can($ability, $user))->toBeTrue()
        ->and($user->can($ability, $other))->toBeFalse();
})->with(['view', 'update']);

test('listing, creating on behalf and sending set-password links belong to the superadmin', function (string $ability, bool $withTarget) {
    $user = User::factory()->create();
    $admin = User::factory()->superadmin()->create();
    $target = User::factory()->create();

    $arguments = $withTarget ? $target : User::class;

    expect($user->can($ability, $arguments))->toBeFalse()
        ->and($admin->can($ability, $arguments))->toBeTrue();
})->with([
    'viewAny' => ['viewAny', false],
    'create' => ['create', false],
    'sendPasswordLink' => ['sendPasswordLink', true],
]);

test('nobody, not even the superadmin, may change a role through the policy', function () {
    $admin = User::factory()->superadmin()->create();
    $target = User::factory()->create();

    expect($admin->can('changeRole', $target))->toBeFalse()
        ->and($admin->can('changeRole', $admin))->toBeFalse();
});
