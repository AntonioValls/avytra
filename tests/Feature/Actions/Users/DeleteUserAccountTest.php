<?php

use App\Actions\Users\DeleteUserAccount;
use App\Models\Business;
use App\Models\Location;
use App\Models\OnlineProfile;
use App\Models\User;

test('deleting an account removes the businesses it owns with their premises and online profiles', function () {
    $user = User::factory()->create();
    $hybrid = Business::factory()->hybrid()->ownedBy($user)->withLocation()->withOnlineProfile()->create();
    $trashed = Business::factory()->ownedBy($user)->create();
    $trashed->delete();
    $foreign = Business::factory()->withLocation()->create();

    app(DeleteUserAccount::class)->handle($user);

    expect(User::find($user->id))->toBeNull()
        ->and(Business::withTrashed()->where('owner_user_id', $user->id)->count())->toBe(0)
        ->and(Location::where('business_id', $hybrid->id)->exists())->toBeFalse()
        ->and(OnlineProfile::where('business_id', $hybrid->id)->exists())->toBeFalse()
        ->and(Business::find($foreign->id))->not->toBeNull()
        ->and(Location::where('business_id', $foreign->id)->exists())->toBeTrue();
});

test('businesses the user created for somebody else survive the deletion of their account', function () {
    $admin = User::factory()->superadmin()->create();
    $business = Business::factory()->createdBy($admin)->create();

    app(DeleteUserAccount::class)->handle($admin);

    expect(Business::find($business->id))->not->toBeNull()
        ->and($business->fresh()->created_by_user_id)->toBeNull();
});
