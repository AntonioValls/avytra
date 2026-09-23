<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * The geocoding provider could not answer (network error, non-2xx response or its rate limit).
 * The owner can always place the pin by hand instead.
 */
class GeocodingUnavailable extends RuntimeException
{
    public static function rateLimited(): self
    {
        return new self('The geocoding provider rate limit was reached.');
    }

    public static function providerFailed(?Throwable $previous = null): self
    {
        return new self('The geocoding provider did not answer.', 0, $previous);
    }

    public function userMessage(): string
    {
        return __('The address search is not available right now. Place the pin on the map by hand or try again in a moment.');
    }
}
