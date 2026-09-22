<?php

use App\Models\Business;
use App\Models\Listing;
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

test('the owner cannot delete a business once one of its listings has been published', function (string $state, bool $expected) {
    $business = Business::factory()->create();
    Listing::factory()->forBusiness($business)->{$state}()->create();

    expect($business->owner->can('delete', $business))->toBe($expected)
        ->and(User::factory()->superadmin()->create()->can('delete', $business))->toBeTrue();
})->with([
    'draft' => ['draft', true],
    'archived draft' => ['archived', true],
    'published' => ['published', false],
    'paused' => ['paused', false],
    'sold' => ['sold', false],
]);
