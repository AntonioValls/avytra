<?php

use App\Exceptions\GeocodingUnavailable;
use App\Services\Geocoding\Geocoder;
use App\Services\Geocoding\NominatimGeocoder;
use App\Services\Geocoding\NullGeocoder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

function nominatim(): NominatimGeocoder
{
    return new NominatimGeocoder(
        baseUrl: 'https://nominatim.test',
        userAgent: 'AVYTRA tests',
        email: 'dev@example.com',
        requestsPerSecond: 1,
        timeoutSeconds: 5,
        cacheDays: 30,
    );
}

/**
 * @return array<int, array<string, mixed>>
 */
function nominatimAnswer(): array
{
    return [[
        'lat' => '39.8888',
        'lon' => '-0.0777',
        'display_name' => 'Calle Mayor 1, Borriana, Castellón, España',
        'importance' => 0.61,
        'address' => ['postcode' => '12530'],
    ]];
}

beforeEach(function () {
    RateLimiter::clear(NominatimGeocoder::RATE_LIMIT_KEY);
});

test('the null driver is bound by default and disables the address search', function () {
    config()->set('avytra.geocoding.driver', 'null');

    $geocoder = app(Geocoder::class);

    expect($geocoder)->toBeInstanceOf(NullGeocoder::class)
        ->and($geocoder->isAvailable())->toBeFalse()
        ->and($geocoder->geocode('Calle Mayor 1'))->toBeNull();
});

test('the nominatim driver is bound from the configuration', function () {
    config()->set('avytra.geocoding.driver', 'nominatim');

    $geocoder = app(Geocoder::class);

    expect($geocoder)->toBeInstanceOf(NominatimGeocoder::class)
        ->and($geocoder->isAvailable())->toBeTrue();
});

test('nominatim identifies itself, restricts the country and parses the first result', function () {
    Http::fake(['nominatim.test/*' => Http::response(nominatimAnswer())]);

    $result = nominatim()->geocode('Calle Mayor 1, Borriana, Castellón');

    expect($result)->not->toBeNull()
        ->and($result->latitude)->toBe(39.8888)
        ->and($result->longitude)->toBe(-0.0777)
        ->and($result->postalCode)->toBe('12530')
        ->and($result->formattedAddress)->toBe('Calle Mayor 1, Borriana, Castellón, España')
        ->and($result->provider)->toBe('nominatim')
        ->and($result->confidence)->toBe(0.61);

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $request->hasHeader('User-Agent', 'AVYTRA tests')
            && str_starts_with($request->url(), 'https://nominatim.test/search?')
            && $query['q'] === 'calle mayor 1, borriana, castellón'
            && $query['countrycodes'] === 'es'
            && $query['limit'] === '1'
            && $query['format'] === 'jsonv2'
            && $query['email'] === 'dev@example.com';
    });
});

test('nominatim caches the answer per normalised query, including misses', function () {
    Http::fake(['nominatim.test/*' => Http::response([])]);

    $geocoder = nominatim();

    expect($geocoder->geocode('Calle Mayor 1, Borriana'))->toBeNull();

    RateLimiter::clear(NominatimGeocoder::RATE_LIMIT_KEY);

    expect($geocoder->geocode('  calle   MAYOR 1,  Borriana '))->toBeNull();

    Http::assertSentCount(1);
});

test('nominatim never sends more than one request per second', function () {
    Http::fake(['nominatim.test/*' => Http::response(nominatimAnswer())]);

    $geocoder = nominatim();
    $geocoder->geocode('Primera consulta');

    expect(fn () => $geocoder->geocode('Segunda consulta'))->toThrow(GeocodingUnavailable::class);

    Http::assertSentCount(1);
});

test('a provider failure raises GeocodingUnavailable with a message for the owner', function () {
    Http::fake(['nominatim.test/*' => Http::response('', 503)]);

    try {
        nominatim()->geocode('Calle Mayor 1');
        $this->fail('GeocodingUnavailable was not thrown.');
    } catch (GeocodingUnavailable $exception) {
        expect($exception->userMessage())->toBe(__('The address search is not available right now. Place the pin on the map by hand or try again in a moment.'));
    }
});

test('an empty query is not sent to the provider', function () {
    Http::fake();

    expect(nominatim()->geocode('   '))->toBeNull();

    Http::assertNothingSent();
});
