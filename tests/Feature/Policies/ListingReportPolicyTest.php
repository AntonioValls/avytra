<?php

use App\Models\ListingReport;
use App\Models\User;

test('only the superadmin may read and resolve reports', function (string $ability) {
    $report = ListingReport::factory()->create();

    expect(User::factory()->create()->can($ability, $report))->toBeFalse()
        ->and($report->listing->owner()->can($ability, $report))->toBeFalse()
        ->and(User::factory()->superadmin()->create()->can($ability, $report))->toBeTrue();
})->with(['view', 'resolve']);

test('listing the inbox is reserved to the superadmin', function () {
    expect(User::factory()->create()->can('viewAny', ListingReport::class))->toBeFalse()
        ->and(User::factory()->superadmin()->create()->can('viewAny', ListingReport::class))->toBeTrue();
});
