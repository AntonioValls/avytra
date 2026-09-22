<?php

use App\Enums\LocationVisibility;
use App\Support\Location\Coordinates;
use App\Support\Location\PublicPointDeriver;

function makeDeriver(string $salt = 'test-salt'): PublicPointDeriver
{
    return new PublicPointDeriver(
        salt: $salt,
        approximateRadiusM: 700,
        minOffsetM: 250,
        maxOffsetM: 600,
        cityDefaultRadiusM: 3000,
        cityRadiusByPopulation: [5000 => 1500, 20000 => 2000, 100000 => 3000, 500000 => 4000],
        cityMaxRadiusM: 5000,
    );
}

function metresBetween(Coordinates $a, Coordinates $b): float
{
    $earthRadius = 6_371_000;
    $dLat = deg2rad($b->latitude - $a->latitude);
    $dLng = deg2rad($b->longitude - $a->longitude);
    $h = sin($dLat / 2) ** 2 + cos(deg2rad($a->latitude)) * cos(deg2rad($b->latitude)) * sin($dLng / 2) ** 2;

    return 2 * $earthRadius * asin(sqrt($h));
}

$madrid = new Coordinates(40.4167754, -3.7037902);
$centre = new Coordinates(40.465429, -3.696466);

test('exact keeps the private point and has no radius', function () use ($madrid, $centre) {
    $point = makeDeriver()->derive(LocationVisibility::Exact, $madrid, '1', $centre, 3_000_000);

    expect($point->latitude)->toBe($madrid->latitude)
        ->and($point->longitude)->toBe($madrid->longitude)
        ->and($point->radiusM)->toBeNull();
});

test('approximate moves the point between 250 and 600 m and keeps it inside the 700 m circle', function () use ($madrid, $centre) {
    foreach (range(1, 50) as $seed) {
        $point = makeDeriver()->derive(LocationVisibility::Approximate, $madrid, (string) $seed, $centre, null);
        $distance = metresBetween($madrid, $point->coordinates());

        expect($point->radiusM)->toBe(700)
            ->and($distance)->toBeGreaterThanOrEqual(249)
            ->and($distance)->toBeLessThanOrEqual(601)
            ->and($distance)->toBeLessThan(700);
    }
});

test('approximate is deterministic for the same salt and seed', function () use ($madrid, $centre) {
    $first = makeDeriver()->derive(LocationVisibility::Approximate, $madrid, '42', $centre, null);
    $second = makeDeriver()->derive(LocationVisibility::Approximate, $madrid, '42', $centre, null);

    expect($second->latitude)->toBe($first->latitude)
        ->and($second->longitude)->toBe($first->longitude);
});

test('changing the salt or the seed moves the public point', function () use ($madrid, $centre) {
    $base = makeDeriver()->derive(LocationVisibility::Approximate, $madrid, '42', $centre, null);
    $otherSeed = makeDeriver()->derive(LocationVisibility::Approximate, $madrid, '43', $centre, null);
    $otherSalt = makeDeriver('another-salt')->derive(LocationVisibility::Approximate, $madrid, '42', $centre, null);

    expect(metresBetween($base->coordinates(), $otherSeed->coordinates()))->toBeGreaterThan(1)
        ->and(metresBetween($base->coordinates(), $otherSalt->coordinates()))->toBeGreaterThan(1);
});

test('exact and approximate fall back to the municipality centre without a private point', function (LocationVisibility $visibility) use ($centre) {
    $point = makeDeriver()->derive($visibility, null, '1', $centre, 3_000_000);

    expect($point->latitude)->toBe($centre->latitude)
        ->and($point->longitude)->toBe($centre->longitude)
        ->and($point->radiusM)->toBe(5000);
})->with([
    'exact' => LocationVisibility::Exact,
    'approximate' => LocationVisibility::Approximate,
]);

test('city only uses the municipality centre with a radius that grows with the population', function (?int $population, int $radius) use ($madrid, $centre) {
    $point = makeDeriver()->derive(LocationVisibility::CityOnly, $madrid, '1', $centre, $population);

    expect($point->latitude)->toBe($centre->latitude)
        ->and($point->longitude)->toBe($centre->longitude)
        ->and($point->radiusM)->toBe($radius);
})->with([
    'unknown population' => [null, 3000],
    'village' => [1200, 1500],
    'just under 5,000' => [4999, 1500],
    'small town' => [5000, 2000],
    'town' => [19999, 2000],
    'city' => [20000, 3000],
    'large city' => [100000, 4000],
    'capital' => [500000, 5000],
    'metropolis' => [3000000, 5000],
]);

test('hidden shows nothing', function () use ($madrid, $centre) {
    $point = makeDeriver()->derive(LocationVisibility::Hidden, $madrid, '1', $centre, 50000);

    expect($point->isNone())->toBeTrue()
        ->and($point->radiusM)->toBeNull();
});

test('city only without a municipality centre shows nothing', function () use ($madrid) {
    $point = makeDeriver()->derive(LocationVisibility::CityOnly, $madrid, '1', null, 50000);

    expect($point->isNone())->toBeTrue();
});
