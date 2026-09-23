<?php

namespace App\Services\Geocoding;

final readonly class GeocodingResult
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public ?string $formattedAddress,
        public ?string $postalCode,
        public string $provider,
        public ?float $confidence,
    ) {}
}
