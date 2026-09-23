<?php

namespace App\Services\Geocoding;

/**
 * Default driver: address search is disabled and the owner places the pin by hand.
 */
final class NullGeocoder implements Geocoder
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function geocode(string $query, ?string $countryCode = 'ES'): ?GeocodingResult
    {
        return null;
    }
}
