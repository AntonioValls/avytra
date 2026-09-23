<?php

namespace App\Services\Geocoding;

use App\Exceptions\GeocodingUnavailable;

/**
 * Turns a free-text address into coordinates. See docs/10-location-and-maps.md.
 *
 * The result is a suggestion the owner reviews on the map before saving; nothing is
 * geocoded in bulk or in the background.
 */
interface Geocoder
{
    /**
     * False for the null driver: forms then hide the "search address" button.
     */
    public function isAvailable(): bool;

    /**
     * @throws GeocodingUnavailable when the provider cannot be reached or is rate limited
     */
    public function geocode(string $query, ?string $countryCode = 'ES'): ?GeocodingResult;
}
