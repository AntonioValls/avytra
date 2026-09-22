<?php

use App\Models\Business;
use App\Models\Category;
use App\Models\User;

test('the authenticated user is recorded as creator and last editor', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $business = Business::factory()->ownedBy($user)->create();

    expect($business->created_by_user_id)->toBe($user->id)
        ->and($business->updated_by_user_id)->toBe($user->id);
});

test('a later edit by another user updates only the last editor', function () {
    $creator = User::factory()->superadmin()->create();
    $business = Business::factory()->createdBy($creator)->create();

    $this->actingAs($business->owner);
    $business->update(['name' => 'Renamed']);

    expect($business->fresh()->created_by_user_id)->toBe($creator->id)
        ->and($business->fresh()->updated_by_user_id)->toBe($business->owner->id);
});

test('explicit authorship set by an action is not overridden by the session user', function () {
    $admin = User::factory()->superadmin()->create();
    $this->actingAs(User::factory()->create());

    $business = new Business(['business_type' => 'physical', 'category_id' => Category::factory()->create()->id, 'name' => 'Explicit']);
    $business->owner()->associate(User::factory()->create());
    $business->created_by_user_id = $admin->id;
    $business->updated_by_user_id = $admin->id;
    $business->save();

    expect($business->fresh()->created_by_user_id)->toBe($admin->id)
        ->and($business->fresh()->updated_by_user_id)->toBe($admin->id);
});

test('records created without a session keep authorship empty', function () {
    $business = Business::factory()->create();

    expect($business->created_by_user_id)->toBeNull()
        ->and($business->updated_by_user_id)->toBeNull();
});
