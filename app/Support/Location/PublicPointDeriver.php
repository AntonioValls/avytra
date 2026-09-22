<?php

namespace App\Support\Location;

use App\Enums\LocationVisibility;

/**
 * Derives the coordinates a listing may show publicly from the private ones.
 *
 * - exact: the real point, no radius.
 * - approximate: the real point moved a deterministic 250–600 m in a deterministic
 *   direction (seeded with the application salt and the location), inside a 700 m circle.
 *   Stable across renders, so nobody can triangulate by refreshing; changing the salt
 *   moves every public point.
 * - city_only: the municipality centroid with a radius that grows with its population.
 * - hidden: nothing.
 *
 * Exact and approximate fall back to city_only when the owner never placed a real point.
 * All numbers come from config('avytra.location'). See docs/10-location-and-maps.md.
 */
final class PublicPointDeriver
{
    /**
     * @param  array<int, int>  $cityRadiusByPopulation  population upper bound (exclusive) => radius in metres
     */
    public function __construct(
        private string $salt,
        private int $approximateRadiusM,
        private int $minOffsetM,
        private int $maxOffsetM,
        private int $cityDefaultRadiusM,
        private array $cityRadiusByPopulation,
        private int $cityMaxRadiusM,
    ) {
        ksort($this->cityRadiusByPopulation);
    }

    public function derive(
        LocationVisibility $visibility,
        ?Coordinates $private,
        string $seed,
        ?Coordinates $municipalityCentre,
        ?int $population,
    ): PublicPoint {
        if ($visibility->needsPrivateCoordinates() && $private === null) {
            $visibility = LocationVisibility::CityOnly;
        }

        return match ($visibility) {
            LocationVisibility::Hidden => PublicPoint::none(),
            LocationVisibility::Exact => new PublicPoint($private?->latitude, $private?->longitude, null),
            LocationVisibility::Approximate => $this->offset($private, $seed),
            LocationVisibility::CityOnly => $municipalityCentre === null
                ? PublicPoint::none()
                : new PublicPoint($municipalityCentre->latitude, $municipalityCentre->longitude, $this->cityRadius($population)),
        };
    }

    private function offset(?Coordinates $private, string $seed): PublicPoint
    {
        if ($private === null) {
            return PublicPoint::none();
        }

        $hash = hash('sha256', $this->salt.':'.$seed);
        $distanceSeed = (int) hexdec(substr($hash, 0, 8));
        $bearingSeed = (int) hexdec(substr($hash, 8, 8));

        $distance = $this->minOffsetM + ($distanceSeed % ($this->maxOffsetM - $this->minOffsetM + 1));
        $bearing = deg2rad($bearingSeed % 360);

        $metresPerDegreeLatitude = 111_320;
        $deltaLatitude = ($distance * cos($bearing)) / $metresPerDegreeLatitude;
        $deltaLongitude = ($distance * sin($bearing)) / ($metresPerDegreeLatitude * cos(deg2rad($private->latitude)));

        return new PublicPoint(
            round($private->latitude + $deltaLatitude, 7),
            round($private->longitude + $deltaLongitude, 7),
            $this->approximateRadiusM,
        );
    }

    private function cityRadius(?int $population): int
    {
        if ($population === null) {
            return $this->cityDefaultRadiusM;
        }

        foreach ($this->cityRadiusByPopulation as $upperBound => $radius) {
            if ($population < $upperBound) {
                return $radius;
            }
        }

        return $this->cityMaxRadiusM;
    }
}
