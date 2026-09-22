<?php

use App\Enums\ContactMethod;
use App\Enums\OperationType;
use App\Enums\PriceDisclosure;
use App\Models\Business;
use App\Models\Listing;
use App\Models\Location;
use App\Models\OnlineProfile;
use App\Support\Listings\ListingPublishabilityValidator;
use App\Support\Listings\ListingTitleSuggester;

function completeListing(string $type = 'physical'): Listing
{
    $business = Business::factory()->{$type}()->create(['description' => str_repeat('Negocio consolidado. ', 12)]);

    if ($business->requiresLocation()) {
        $business->location()->create(Location::factory()->make(['business_id' => null])->only(['is_primary', 'country_code', 'province_id', 'municipality_id', 'location_visibility']));
    }

    if ($business->requiresOnlineProfile()) {
        $business->onlineProfile()->create(OnlineProfile::factory()->make()->only(['online_business_type', 'monthly_visits_disclosure']));
    }

    return Listing::factory()->forBusiness($business)->create();
}

test('a complete listing passes for every business type', function (string $type) {
    $report = app(ListingPublishabilityValidator::class)->validate(completeListing($type));

    expect($report->passes())->toBeTrue()->and($report->messages())->toBe([]);
})->with(['physical', 'online', 'hybrid']);

test('each missing mandatory field is reported with the step that fixes it', function (callable $break, int $step) {
    $listing = completeListing();
    $break($listing);

    $report = app(ListingPublishabilityValidator::class)->validate($listing->fresh());

    expect($report->fails())->toBeTrue()
        ->and($report->forStep($step))->toHaveCount(1);
})->with([
    'no operation types' => [fn (Listing $listing) => $listing->operationTypes()->delete(), 1],
    'primary not among the offered' => [fn (Listing $listing) => $listing->forceFill(['primary_operation_type' => OperationType::Other])->save(), 1],
    'short description' => [fn (Listing $listing) => $listing->business->update(['description' => 'Corta']), 2],
    'exact price without amount' => [fn (Listing $listing) => $listing->forceFill(['price_disclosure' => PriceDisclosure::Exact, 'asking_price' => null])->save(), 4],
    'range with min above max' => [fn (Listing $listing) => $listing->forceFill(['price_disclosure' => PriceDisclosure::Range, 'asking_price_min' => 500, 'asking_price_max' => 100])->save(), 4],
    'no municipality' => [fn (Listing $listing) => $listing->business->location->update(['municipality_id' => null]), 5],
    'no preferred contact method' => [fn (Listing $listing) => $listing->forceFill(['preferred_contact_method' => null])->save(), 6],
    'preferred channel empty' => [fn (Listing $listing) => $listing->forceFill(['preferred_contact_method' => ContactMethod::Phone, 'contact_phone' => null])->save(), 6],
    'no title' => [fn (Listing $listing) => $listing->forceFill(['title' => null])->save(), 8],
    'title too long' => [fn (Listing $listing) => $listing->forceFill(['title' => str_repeat('a', 91)])->save(), 8],
]);

test('an online business needs its online profile and a physical one its premises', function () {
    $online = completeListing('online');
    $online->business->onlineProfile()->delete();

    $physical = completeListing('physical');
    $physical->business->location()->delete();

    $validator = app(ListingPublishabilityValidator::class);

    expect($validator->validate($online->fresh())->forStep(5))->toHaveCount(1)
        ->and($validator->validate($physical->fresh())->forStep(5))->toHaveCount(1);
});

test('the suggested title combines the main operation, the sector and the place', function () {
    $physical = completeListing('physical');
    $physical->business->category->update(['name' => 'Panadería']);
    $physical->business->location->municipality->update(['name' => 'Castellón de la Plana']);
    $physical->forceFill(['primary_operation_type' => OperationType::Transfer])->save();

    $online = completeListing('online');
    $online->business->category->update(['name' => 'Comercio electrónico']);
    $online->forceFill(['primary_operation_type' => OperationType::FullSale])->save();

    $suggester = app(ListingTitleSuggester::class);

    expect($suggester->suggest($physical->fresh()))->toBe('Traspaso de panadería en Castellón de la Plana')
        ->and($suggester->suggest($online->fresh()))->toBe('Venta de comercio electrónico online');
});
