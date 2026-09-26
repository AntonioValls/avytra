<?php

use App\Models\ContactRequest;
use App\Models\User;

test('the owner of the listing and the superadmin may read a message, other users may not', function (string $ability) {
    $request = ContactRequest::factory()->create();

    expect($request->listing->owner()->can($ability, $request))->toBeTrue()
        ->and(User::factory()->superadmin()->create()->can($ability, $request))->toBeTrue()
        ->and(User::factory()->create()->can($ability, $request))->toBeFalse();
})->with(['view', 'markAsRead']);

test('any user may open the messages page, the query is scoped to their listings', function () {
    expect(User::factory()->create()->can('viewAny', ContactRequest::class))->toBeTrue();
});
