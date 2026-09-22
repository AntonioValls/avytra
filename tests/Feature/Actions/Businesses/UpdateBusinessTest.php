<?php

use App\Actions\Businesses\UpdateBusiness;
use App\Enums\BusinessType;
use App\Enums\OnlineBusinessType;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Location;
use App\Models\OnlineProfile;
use App\Models\User;

test('the owner updates the business and is recorded as last editor without an audit entry', function () {
    $business = Business::factory()->create(['name' => 'Before']);

    app(UpdateBusiness::class)->handle($business, $business->owner, ['name' => 'After']);

    expect($business->fresh()->name)->toBe('After')
        ->and($business->fresh()->updated_by_user_id)->toBe($business->owner->id)
        ->and(AuditLog::count())->toBe(0);
});

test('the superadmin editing somebody else\'s business is audited with the changed fields', function () {
    $admin = User::factory()->superadmin()->create();
    $business = Business::factory()->create(['name' => 'Before', 'tagline' => 'Same']);

    app(UpdateBusiness::class)->handle($business, $admin, ['name' => 'After', 'tagline' => 'Same']);

    $log = AuditLog::sole();

    expect($log->action)->toBe('business.updated_by_admin')
        ->and($log->actor_user_id)->toBe($admin->id)
        ->and($log->on_behalf_of_user_id)->toBe($business->owner->id)
        ->and($log->changes)->toBe(['before' => ['name' => 'Before'], 'after' => ['name' => 'After']]);
});

test('turning a hybrid business into an online one removes its premises', function () {
    $business = Business::factory()->hybrid()->withLocation()->withOnlineProfile()->create();

    app(UpdateBusiness::class)->handle($business, $business->owner, ['business_type' => BusinessType::Online->value]);

    expect(Location::where('business_id', $business->id)->exists())->toBeFalse()
        ->and(OnlineProfile::where('business_id', $business->id)->exists())->toBeTrue();
});

test('turning a hybrid business into a physical one removes its online profile', function () {
    $business = Business::factory()->hybrid()->withLocation()->withOnlineProfile()->create();

    app(UpdateBusiness::class)->handle($business, $business->owner, ['business_type' => BusinessType::Physical->value]);

    expect(OnlineProfile::where('business_id', $business->id)->exists())->toBeFalse()
        ->and(Location::where('business_id', $business->id)->exists())->toBeTrue();
});

test('the online profile is updated in place when new data is given', function () {
    $business = Business::factory()->online()->withOnlineProfile()->create();

    app(UpdateBusiness::class)->handle($business, $business->owner, [], [
        'online_business_type' => OnlineBusinessType::Marketplace->value,
        'monthly_visits' => 12345,
    ]);

    expect(OnlineProfile::where('business_id', $business->id)->count())->toBe(1)
        ->and($business->fresh()->onlineProfile->online_business_type)->toBe(OnlineBusinessType::Marketplace)
        ->and($business->fresh()->onlineProfile->monthly_visits)->toBe(12345);
});
