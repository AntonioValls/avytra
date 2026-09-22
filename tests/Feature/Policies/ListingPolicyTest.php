<?php

use App\Models\Business;
use App\Models\Listing;
use App\Models\User;

test('the owner may view and manage the lifecycle of their listing', function (string $ability) {
    $listing = Listing::factory()->published()->create();

    expect($listing->owner()->can($ability, $listing))->toBeTrue();
})->with(['view', 'update', 'publish', 'pause', 'resume', 'confirm', 'markSold', 'archive']);

test('another user cannot touch a listing they do not own', function (string $ability) {
    $listing = Listing::factory()->published()->create();

    expect(User::factory()->create()->can($ability, $listing))->toBeFalse();
})->with(['view', 'update', 'publish', 'pause', 'resume', 'confirm', 'markSold', 'archive', 'delete']);

test('moderation and URL changes are reserved to the superadmin', function (string $ability) {
    $listing = Listing::factory()->published()->create();

    expect($listing->owner()->can($ability, $listing))->toBeFalse()
        ->and(User::factory()->superadmin()->create()->can($ability, $listing))->toBeTrue();
})->with(['suspend', 'unsuspend', 'changeSlug', 'restore', 'forceDelete']);

test('the owner can edit a draft, published, paused or expired listing but not an archived or suspended one', function (string $state, bool $expected) {
    $listing = Listing::factory()->{$state}()->create();

    expect($listing->owner()->can('update', $listing))->toBe($expected);
})->with([
    'draft' => ['draft', true],
    'published' => ['published', true],
    'paused' => ['paused', true],
    'expired' => ['expired', true],
    'sold' => ['sold', true],
    'archived' => ['archived', false],
    'suspended' => ['suspended', false],
]);

test('only drafts can be deleted, and only by their owner', function () {
    $draft = Listing::factory()->draft()->create();
    $published = Listing::factory()->published()->create();

    expect($draft->owner()->can('delete', $draft))->toBeTrue()
        ->and($published->owner()->can('delete', $published))->toBeFalse()
        ->and(User::factory()->superadmin()->create()->can('delete', $published))->toBeTrue();
});

test('a listing can be created only for a business the user owns', function () {
    $business = Business::factory()->create();

    expect($business->owner->can('create', [Listing::class, $business]))->toBeTrue()
        ->and(User::factory()->create()->can('create', [Listing::class, $business]))->toBeFalse()
        ->and(User::factory()->superadmin()->create()->can('create', [Listing::class, $business]))->toBeTrue();
});

test('the superadmin passes every listing ability', function (string $ability) {
    $listing = Listing::factory()->suspended()->create();

    expect(User::factory()->superadmin()->create()->can($ability, $listing))->toBeTrue();
})->with(['view', 'update', 'publish', 'pause', 'resume', 'confirm', 'markSold', 'archive', 'delete', 'suspend', 'unsuspend', 'changeSlug']);
