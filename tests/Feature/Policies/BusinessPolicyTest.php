<?php

use App\Models\Business;
use App\Models\User;

test('the owner can view, update and delete their business', function (string $ability) {
    $business = Business::factory()->create();

    expect($business->owner->can($ability, $business))->toBeTrue();
})->with(['view', 'update', 'delete']);

test('another user cannot view, update or delete a business they do not own', function (string $ability) {
    $business = Business::factory()->create();

    expect(User::factory()->create()->can($ability, $business))->toBeFalse();
})->with(['view', 'update', 'delete']);

test('the owner cannot transfer, restore or force delete their own business', function (string $ability) {
    $business = Business::factory()->create();

    expect($business->owner->can($ability, $business))->toBeFalse();
})->with(['transferOwnership', 'restore', 'forceDelete']);

test('the superadmin can do everything with any business', function (string $ability) {
    $business = Business::factory()->create();

    expect(User::factory()->superadmin()->create()->can($ability, $business))->toBeTrue();
})->with(['view', 'update', 'delete', 'transferOwnership', 'restore', 'forceDelete']);

test('any registered user can list and create businesses', function () {
    $user = User::factory()->create();

    expect($user->can('viewAny', Business::class))->toBeTrue()
        ->and($user->can('create', Business::class))->toBeTrue();
});
