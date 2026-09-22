<?php

use App\Actions\Listings\CreateListingDraft;
use App\Actions\Listings\DeleteListingDraft;
use App\Actions\Listings\UpdateListing;
use App\Enums\Disclosure;
use App\Enums\FinancialMetric;
use App\Enums\ListingEventType;
use App\Enums\ListingStatus;
use App\Enums\OperationType;
use App\Exceptions\BusinessAlreadyListed;
use App\Exceptions\InvalidListingTransition;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Listing;
use App\Models\ListingFinancialMetric;
use App\Models\User;

test('a draft is created with its operation types, a primary one and a creation event', function () {
    $business = Business::factory()->create();

    $listing = app(CreateListingDraft::class)->handle($business, $business->owner, [], [OperationType::FullSale, OperationType::PartnerEntry]);

    expect($listing->status)->toBe(ListingStatus::Draft)
        ->and($listing->business_id)->toBe($business->id)
        ->and($listing->created_by_user_id)->toBe($business->owner->id)
        ->and($listing->offeredOperationTypes())->toBe([OperationType::FullSale, OperationType::PartnerEntry])
        ->and($listing->primary_operation_type)->toBe(OperationType::FullSale)
        ->and($listing->events()->sole()->type)->toBe(ListingEventType::Created)
        ->and(AuditLog::count())->toBe(0);
});

test('a business cannot have two listings that are not sold nor archived', function (string $state) {
    $business = Business::factory()->create();
    Listing::factory()->forBusiness($business)->{$state}()->create();

    app(CreateListingDraft::class)->handle($business, $business->owner);
})->with(['draft', 'published', 'paused', 'expired', 'suspended'])->throws(BusinessAlreadyListed::class);

test('a business with a sold or archived listing can be listed again', function (string $state) {
    $business = Business::factory()->create();
    Listing::factory()->forBusiness($business)->{$state}()->create();

    $listing = app(CreateListingDraft::class)->handle($business, $business->owner, [], [OperationType::Transfer]);

    expect($listing->exists)->toBeTrue()
        ->and($business->listings()->count())->toBe(2);
})->with(['sold', 'archived']);

test('publishing again copies the content, the operations and the metrics of the previous listing but not its title or lifecycle', function () {
    $business = Business::factory()->create();
    $previous = Listing::factory()->forBusiness($business)->sold()->offering([OperationType::Transfer, OperationType::AssetSale])->create([
        'reason_for_sale' => 'Jubilación',
        'contact_phone' => '+34600000000',
    ]);
    ListingFinancialMetric::factory()->for($previous)->metric(FinancialMetric::AnnualRevenue)->create(['amount' => 120000]);

    $listing = app(CreateListingDraft::class)->handle($business, $business->owner, [], [], $previous);

    expect($listing->status)->toBe(ListingStatus::Draft)
        ->and($listing->title)->toBeNull()
        ->and($listing->slug)->toBeNull()
        ->and($listing->published_at)->toBeNull()
        ->and($listing->reason_for_sale)->toBe('Jubilación')
        ->and($listing->contact_phone)->toBe('+34600000000')
        ->and($listing->offeredOperationTypes())->toBe([OperationType::Transfer, OperationType::AssetSale])
        ->and($listing->financialMetrics()->sole()->amount)->toBe(120000)
        ->and($listing->events()->sole()->payload)->toBe(['copied_from_listing_id' => $previous->id]);
});

test('the superadmin creating a draft for another user is audited', function () {
    $admin = User::factory()->superadmin()->create();
    $business = Business::factory()->create();

    $listing = app(CreateListingDraft::class)->handle($business, $admin, [], [OperationType::Transfer]);

    expect($listing->created_by_user_id)->toBe($admin->id)
        ->and(AuditLog::where('action', 'listing.created_by_admin')->where('on_behalf_of_user_id', $business->owner->id)->exists())->toBeTrue();
});

test('updating a listing syncs its operation types and financial metrics', function () {
    $listing = Listing::factory()->offering([OperationType::Transfer, OperationType::AssetSale])->create();
    ListingFinancialMetric::factory()->for($listing)->metric(FinancialMetric::Ebitda)->create();

    app(UpdateListing::class)->handle($listing, $listing->owner(), ['reason_for_sale' => 'Cambio de ciudad'], [OperationType::AssetSale, OperationType::Other], [
        FinancialMetric::AnnualRevenue->value => ['disclosure' => Disclosure::Range->value, 'amount_min' => 100000, 'amount_max' => 150000],
        FinancialMetric::MonthlyRent->value => ['disclosure' => Disclosure::Exact->value, 'amount' => 900],
    ]);

    $listing->refresh();

    expect($listing->reason_for_sale)->toBe('Cambio de ciudad')
        ->and($listing->offeredOperationTypes())->toBe([OperationType::AssetSale, OperationType::Other])
        ->and($listing->financialMetrics->pluck('metric')->map(fn (FinancialMetric $metric) => $metric->value)->sort()->values()->all())->toBe(['annual_revenue', 'monthly_rent'])
        ->and($listing->financialMetrics->firstWhere('metric', FinancialMetric::AnnualRevenue)?->amount_max)->toBe(150000)
        ->and(AuditLog::count())->toBe(0);
});

test('the superadmin editing somebody else\'s listing leaves an audit entry with the changed fields', function () {
    $admin = User::factory()->superadmin()->create();
    $listing = Listing::factory()->create(['reason_for_sale' => 'Antes']);

    app(UpdateListing::class)->handle($listing, $admin, ['reason_for_sale' => 'Después']);

    $log = AuditLog::where('action', 'listing.updated_by_admin')->sole();

    expect($log->changes)->toBe(['before' => ['reason_for_sale' => 'Antes'], 'after' => ['reason_for_sale' => 'Después']])
        ->and($listing->updated_by_user_id)->toBe($admin->id);
});

test('a draft is physically deleted with its rows; anything else refuses', function () {
    $draft = Listing::factory()->draft()->create();
    $published = Listing::factory()->published()->create();

    app(DeleteListingDraft::class)->handle($draft, $draft->owner());

    expect(Listing::withTrashed()->find($draft->id))->toBeNull();

    expect(fn () => app(DeleteListingDraft::class)->handle($published, $published->owner()))->toThrow(InvalidListingTransition::class);
});
