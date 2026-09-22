<?php

namespace App\Models;

use App\Enums\GeocodingSource;
use App\Enums\LocationVisibility;
use App\Support\Location\Coordinates;
use App\Support\Location\PublicPoint;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Premises of a physical or hybrid business.
 *
 * latitude/longitude/address_line/postal_code are private. The public_* columns are
 * derived by SaveBusinessLocation and are the only coordinates that may leave the server.
 *
 * @property int $id
 * @property int $business_id
 * @property bool $is_primary
 * @property string $country_code
 * @property int $province_id
 * @property int|null $municipality_id
 * @property string|null $postal_code
 * @property string|null $address_line
 * @property float|null $latitude
 * @property float|null $longitude
 * @property LocationVisibility $location_visibility
 * @property float|null $public_latitude
 * @property float|null $public_longitude
 * @property int|null $public_radius_m
 * @property GeocodingSource|null $geocoding_source
 * @property string|null $geocoding_provider
 * @property Carbon|null $geocoded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'is_primary',
    'country_code',
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
])]
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'public_latitude' => 'float',
            'public_longitude' => 'float',
            'public_radius_m' => 'integer',
            'location_visibility' => LocationVisibility::class,
            'geocoding_source' => GeocodingSource::class,
            'geocoded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * @return BelongsTo<Municipality, $this>
     */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function privateCoordinates(): ?Coordinates
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }

        return new Coordinates($this->latitude, $this->longitude);
    }

    /**
     * The visibility actually applied: without a real point, "exact" and "approximate"
     * cannot be honoured, so the location falls back to the municipality.
     */
    public function effectiveVisibility(): LocationVisibility
    {
        if ($this->location_visibility->needsPrivateCoordinates() && $this->privateCoordinates() === null) {
            return LocationVisibility::CityOnly;
        }

        return $this->location_visibility;
    }

    public function publicPoint(): PublicPoint
    {
        return new PublicPoint($this->public_latitude, $this->public_longitude, $this->public_radius_m);
    }

    public function applyPublicPoint(PublicPoint $point): void
    {
        $this->public_latitude = $point->latitude;
        $this->public_longitude = $point->longitude;
        $this->public_radius_m = $point->radiusM;
    }
}
