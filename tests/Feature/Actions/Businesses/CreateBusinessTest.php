<?php

use App\Actions\Businesses\CreateBusiness;
use App\Enums\BusinessType;
use App\Enums\OnlineBusinessType;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\User;

function businessAttributes(BusinessType $type = BusinessType::Physical): array
{
    return [
        'business_type' => $type->value,
        'category_id' => Category::factory()->create()->id,
        'name' => 'Panadería Sol',
        'website_visibility' => 'private',
    ];
}

test('an owner creating their own business is recorded as creator and nothing is audited', function () {
    $owner = User::factory()->create();

    $business = app(CreateBusiness::class)->handle($owner, $owner, businessAttributes());

    expect($business->owner_user_id)->toBe($owner->id)
        ->and($business->created_by_user_id)->toBe($owner->id)
        ->and($business->updated_by_user_id)->toBe($owner->id)
        ->and(AuditLog::count())->toBe(0);
});

test('the superadmin creating a business for another user does not become its owner and the action is audited', function () {
    $admin = User::factory()->superadmin()->create();
    $owner = User::factory()->create();

    $business = app(CreateBusiness::class)->handle($owner, $admin, businessAttributes());

    expect($business->owner_user_id)->toBe($owner->id)
        ->and($business->created_by_user_id)->toBe($admin->id);

    $log = AuditLog::sole();

    expect($log->action)->toBe('business.created_by_admin')
        ->and($log->actor_user_id)->toBe($admin->id)
        ->and($log->on_behalf_of_user_id)->toBe($owner->id)
        ->and($log->subject->is($business))->toBeTrue();
});

test('an online business is created together with its online profile', function () {
    $owner = User::factory()->create();

    $business = app(CreateBusiness::class)->handle($owner, $owner, businessAttributes(BusinessType::Online), [
        'online_business_type' => OnlineBusinessType::Saas->value,
        'monthly_visits_disclosure' => 'exact',
    ]);

    expect($business->onlineProfile)->not->toBeNull()
        ->and($business->onlineProfile->online_business_type)->toBe(OnlineBusinessType::Saas);
});

test('a physical business rejects an online profile', function () {
    $owner = User::factory()->create();

    app(CreateBusiness::class)->handle($owner, $owner, businessAttributes(), [
        'online_business_type' => OnlineBusinessType::Saas->value,
    ]);
})->throws(InvalidArgumentException::class);
