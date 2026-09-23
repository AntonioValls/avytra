<?php

namespace App\Actions\Locations;

use App\Enums\GeocodingSource;
use App\Models\Business;
use App\Models\Location;
use App\Models\Municipality;
use App\Support\Location\PublicPointDeriver;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates or updates the primary location of a physical/hybrid business and
 * recalculates the public coordinates. This is the only place that writes public_*.
 */
class SaveBusinessLocation
{
    /**
     * Attributes accepted from forms. business_id, is_primary and public_* are never taken from input.
     *
     * @var list<string>
     */
    private const array ACCEPTED = [
        'province_id',
        'municipality_id',
        'postal_code',
        'address_line',
        'latitude',
        'longitude',
        'location_visibility',
        'geocoding_source',
        'geocoding_provider',
        'geocoded_at',
    ];

    public function __construct(private PublicPointDeriver $deriver) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Business $business, array $attributes): Location
    {
        throw_unless(
            $business->requiresLocation(),
            new InvalidArgumentException('An online business has no premises.'),
        );

        return DB::transaction(function () use ($business, $attributes): Location {
            $location = $business->location ?? new Location(['is_primary' => true]);
            $location->fill(Arr::only($attributes, self::ACCEPTED));
            $location->business()->associate($business);
            $location->geocoding_source = $this->resolveSource($location);
            $this->syncGeocodingTrace($location);

            // Save first so a new location has an id to seed the deterministic offset with.
            $location->save();

            $municipality = $location->municipality_id === null
                ? null
                : Municipality::query()->find($location->municipality_id);

            $location->applyPublicPoint($this->deriver->derive(
                visibility: $location->location_visibility,
                private: $location->privateCoordinates(),
                seed: (string) $location->getKey(),
                municipalityCentre: $municipality?->centre(),
                population: $municipality?->population,
            ));
            $location->save();

            $business->setRelation('location', $location);

            return $location;
        });
    }

    private function resolveSource(Location $location): ?GeocodingSource
    {
        if ($location->privateCoordinates() === null) {
            return $location->municipality_id === null ? null : GeocodingSource::MunicipalityCentroid;
        }

        if ($location->geocoding_source === null || $location->geocoding_source === GeocodingSource::MunicipalityCentroid) {
            return GeocodingSource::ManualPin;
        }

        return $location->geocoding_source;
    }

    /**
     * Provider and timestamp only make sense for geocoded points; a hand-placed pin clears them.
     */
    private function syncGeocodingTrace(Location $location): void
    {
        if ($location->geocoding_source !== GeocodingSource::Geocoder) {
            $location->geocoding_provider = null;
            $location->geocoded_at = null;

            return;
        }

        if ($location->geocoded_at === null || $location->isDirty(['latitude', 'longitude'])) {
            $location->geocoded_at = now();
        }
    }
}
