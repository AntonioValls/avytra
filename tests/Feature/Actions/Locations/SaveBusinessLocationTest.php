<?php

use App\Actions\Locations\SaveBusinessLocation;
use App\Enums\GeocodingSource;
use App\Enums\LocationVisibility;
use App\Models\Business;
use App\Models\Municipality;
use App\Models\Province;

function locationAttributes(Municipality $municipality, array $overrides = []): array
{
    return array_merge([
        'province_id' => $municipality->province_id,
        'municipality_id' => $municipality->id,
        'address_line' => 'Calle Mayor 1',
        'postal_code' => '12001',
        'location_visibility' => LocationVisibility::Approximate->value,
    ], $overrides);
}

function metresApart(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $h = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

    return 2 * 6_371_000 * asin(sqrt($h));
}

test('without a real point the municipality centre is published with a population-based radius', function () {
    $municipality = Municipality::factory()->withPopulation(12000)->create(['latitude' => 39.98, 'longitude' => -0.05]);
    $business = Business::factory()->physical()->create();

    $location = app(SaveBusinessLocation::class)->handle($business, locationAttributes($municipality));

    expect($location->public_latitude)->toBe(39.98)
        ->and($location->public_longitude)->toBe(-0.05)
        ->and($location->public_radius_m)->toBe(2000)
        ->and($location->geocoding_source)->toBe(GeocodingSource::MunicipalityCentroid)
        ->and($location->effectiveVisibility())->toBe(LocationVisibility::CityOnly)
        ->and($location->is_primary)->toBeTrue()
        ->and($business->fresh()->location->is($location))->toBeTrue();
});

test('an approximate location with a real point publishes a moved point inside a 700 m circle', function () {
    $municipality = Municipality::factory()->create();
    $business = Business::factory()->physical()->create();

    $location = app(SaveBusinessLocation::class)->handle($business, locationAttributes($municipality, [
        'latitude' => 40.4167754,
        'longitude' => -3.7037902,
    ]));

    $distance = metresApart(40.4167754, -3.7037902, $location->public_latitude, $location->public_longitude);

    expect($distance)->toBeGreaterThan(200)
        ->and($distance)->toBeLessThan(700)
        ->and($location->public_radius_m)->toBe(700)
        ->and($location->geocoding_source)->toBe(GeocodingSource::ManualPin)
        ->and($location->latitude)->toBe(40.4167754);
});

test('an exact location publishes the real point without radius', function () {
    $municipality = Municipality::factory()->create();
    $business = Business::factory()->physical()->create();

    $location = app(SaveBusinessLocation::class)->handle($business, locationAttributes($municipality, [
        'latitude' => 40.4167754,
        'longitude' => -3.7037902,
        'location_visibility' => LocationVisibility::Exact->value,
    ]));

    expect($location->public_latitude)->toBe(40.4167754)
        ->and($location->public_longitude)->toBe(-3.7037902)
        ->and($location->public_radius_m)->toBeNull();
});

test('a hidden location publishes no coordinates at all', function () {
    $municipality = Municipality::factory()->create();
    $business = Business::factory()->physical()->create();

    $location = app(SaveBusinessLocation::class)->handle($business, locationAttributes($municipality, [
        'latitude' => 40.4167754,
        'longitude' => -3.7037902,
        'location_visibility' => LocationVisibility::Hidden->value,
    ]));

    expect($location->public_latitude)->toBeNull()
        ->and($location->public_longitude)->toBeNull()
        ->and($location->public_radius_m)->toBeNull();
});

test('saving again updates the same location and recalculates the public point', function () {
    $municipality = Municipality::factory()->withPopulation(800)->create(['latitude' => 41.0, 'longitude' => 1.0]);
    $business = Business::factory()->physical()->create();
    $action = app(SaveBusinessLocation::class);

    $first = $action->handle($business, locationAttributes($municipality, ['latitude' => 41.001, 'longitude' => 1.001]));
    $second = $action->handle($business->fresh(), locationAttributes($municipality, ['location_visibility' => LocationVisibility::CityOnly->value]));

    expect($second->id)->toBe($first->id)
        ->and($second->public_latitude)->toBe(41.0)
        ->and($second->public_radius_m)->toBe(1500)
        ->and($business->fresh()->location()->count())->toBe(1);
});

test('input cannot set the public coordinates or the business directly', function () {
    $municipality = Municipality::factory()->create(['latitude' => 41.0, 'longitude' => 1.0]);
    $business = Business::factory()->physical()->create();
    $other = Business::factory()->physical()->create();

    $location = app(SaveBusinessLocation::class)->handle($business, locationAttributes($municipality, [
        'business_id' => $other->id,
        'public_latitude' => 0.0,
        'public_longitude' => 0.0,
        'public_radius_m' => 1,
    ]));

    expect($location->business_id)->toBe($business->id)
        ->and($location->public_latitude)->toBe(41.0)
        ->and($location->public_radius_m)->not->toBe(1);
});

test('an online business cannot have premises', function () {
    $municipality = Municipality::factory()->create();
    $business = Business::factory()->online()->create();

    app(SaveBusinessLocation::class)->handle($business, locationAttributes($municipality));
})->throws(InvalidArgumentException::class);

test('a municipality of another province is still stored as given: coherence is validated by the form', function () {
    $municipality = Municipality::factory()->create();
    $otherProvince = Province::factory()->create();
    $business = Business::factory()->physical()->create();

    $location = app(SaveBusinessLocation::class)->handle($business, locationAttributes($municipality, ['province_id' => $otherProvince->id]));

    expect($location->province_id)->toBe($otherProvince->id);
});
