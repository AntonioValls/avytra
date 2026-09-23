<?php

namespace App\Services\Geocoding;

use App\Exceptions\GeocodingUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

/**
 * OpenStreetMap Nominatim. Development / low-volume only: its usage policy requires an
 * identifying User-Agent, at most one request per second and no bulk geocoding.
 * Results (including misses) are cached per normalised query.
 */
final class NominatimGeocoder implements Geocoder
{
    public const string PROVIDER = 'nominatim';

    public const string RATE_LIMIT_KEY = 'geocoding:nominatim';

    public function __construct(
        private string $baseUrl,
        private string $userAgent,
        private ?string $email,
        private int $requestsPerSecond,
        private int $timeoutSeconds,
        private int $cacheDays,
    ) {}

    public function isAvailable(): bool
    {
        return true;
    }

    public function geocode(string $query, ?string $countryCode = 'ES'): ?GeocodingResult
    {
        $normalised = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $query)));

        if ($normalised === '') {
            return null;
        }

        $cacheKey = self::RATE_LIMIT_KEY.':'.sha1($normalised.'|'.strtolower((string) $countryCode));

        /** @var array{result: GeocodingResult|null} $cached */
        $cached = Cache::remember($cacheKey, now()->addDays($this->cacheDays), fn (): array => [
            'result' => $this->request($normalised, $countryCode),
        ]);

        return $cached['result'];
    }

    /**
     * @throws GeocodingUnavailable
     */
    private function request(string $query, ?string $countryCode): ?GeocodingResult
    {
        if (RateLimiter::tooManyAttempts(self::RATE_LIMIT_KEY, $this->requestsPerSecond)) {
            throw GeocodingUnavailable::rateLimited();
        }

        RateLimiter::hit(self::RATE_LIMIT_KEY, 1);

        $parameters = array_filter([
            'q' => $query,
            'format' => 'jsonv2',
            'limit' => 1,
            'addressdetails' => 1,
            'countrycodes' => $countryCode === null ? null : strtolower($countryCode),
            'email' => $this->email,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        try {
            $response = Http::withHeaders(['User-Agent' => $this->userAgent])
                ->acceptJson()
                ->timeout($this->timeoutSeconds)
                ->get(rtrim($this->baseUrl, '/').'/search', $parameters);
        } catch (ConnectionException $exception) {
            throw GeocodingUnavailable::providerFailed($exception);
        }

        if (! $response->successful()) {
            throw GeocodingUnavailable::providerFailed();
        }

        /** @var array<int, array<string, mixed>> $results */
        $results = $response->json();
        $first = $results[0] ?? null;

        if (! is_array($first) || ! isset($first['lat'], $first['lon'])) {
            return null;
        }

        /** @var array<string, mixed> $address */
        $address = is_array($first['address'] ?? null) ? $first['address'] : [];

        return new GeocodingResult(
            latitude: (float) $first['lat'],
            longitude: (float) $first['lon'],
            formattedAddress: isset($first['display_name']) ? (string) $first['display_name'] : null,
            postalCode: isset($address['postcode']) ? (string) $address['postcode'] : null,
            provider: self::PROVIDER,
            confidence: isset($first['importance']) ? (float) $first['importance'] : null,
        );
    }
}
