<?php

namespace App\Livewire\Forms;

use App\Enums\GeocodingSource;
use App\Enums\LocationVisibility;
use App\Exceptions\GeocodingUnavailable;
use App\Models\Location;
use App\Models\Municipality;
use App\Services\Geocoding\Geocoder;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Premises of a physical or hybrid business. Coordinates come from the map picker (a pin
 * placed by the owner or an address search); without them the municipality centre is used.
 */
class LocationForm extends Form
{
    public ?int $province_id = null;

    public ?int $municipality_id = null;

    public string $postal_code = '';

    public string $address_line = '';

    public ?float $latitude = null;

    public ?float $longitude = null;

    public string $location_visibility = LocationVisibility::Approximate->value;

    public ?string $geocoding_source = null;

    public ?string $geocoding_provider = null;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'province_id' => ['required', 'integer', Rule::exists('provinces', 'id')],
            'municipality_id' => ['required', 'integer', Rule::exists('municipalities', 'id')->where('province_id', $this->province_id)],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'location_visibility' => ['required', Rule::enum(LocationVisibility::class)],
            'geocoding_source' => ['nullable', Rule::enum(GeocodingSource::class)],
            'geocoding_provider' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'province_id' => __('province'),
            'municipality_id' => __('municipality'),
            'postal_code' => __('postal code'),
            'address_line' => __('address'),
            'latitude' => __('latitude'),
            'longitude' => __('longitude'),
            'location_visibility' => __('location visibility'),
            'geocoding_source' => __('geocoding source'),
            'geocoding_provider' => __('geocoding provider'),
        ];
    }

    public function fillFromLocation(Location $location): void
    {
        $this->province_id = $location->province_id;
        $this->municipality_id = $location->municipality_id;
        $this->postal_code = $location->postal_code ?? '';
        $this->address_line = $location->address_line ?? '';
        $this->latitude = $location->latitude;
        $this->longitude = $location->longitude;
        $this->location_visibility = $location->location_visibility->value;
        $this->geocoding_source = $location->geocoding_source?->value;
        $this->geocoding_provider = $location->geocoding_provider;
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'province_id' => $this->province_id,
            'municipality_id' => $this->municipality_id,
            'postal_code' => $this->nullable($this->postal_code),
            'address_line' => $this->nullable($this->address_line),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'location_visibility' => $this->location_visibility,
            'geocoding_source' => $this->hasPoint() ? $this->geocoding_source : null,
            'geocoding_provider' => $this->hasPoint() ? $this->geocoding_provider : null,
        ];
    }

    public function hasPoint(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Exact and approximate visibility need a real point; without it the location is
     * published as "municipality only" (Location::effectiveVisibility()).
     */
    public function willFallBackToMunicipality(): bool
    {
        $visibility = LocationVisibility::tryFrom($this->location_visibility);

        return $visibility !== null && $visibility->needsPrivateCoordinates() && ! $this->hasPoint();
    }

    /**
     * A pin dragged or clicked on the map overrides whatever placed it before.
     */
    public function markManualPin(): void
    {
        $this->geocoding_source = $this->hasPoint() ? GeocodingSource::ManualPin->value : null;
        $this->geocoding_provider = null;
    }

    public function clearPoint(): void
    {
        $this->latitude = null;
        $this->longitude = null;
        $this->geocoding_source = null;
        $this->geocoding_provider = null;
    }

    public function validateForAddressSearch(): void
    {
        $this->validate([
            'province_id' => ['required', 'integer', Rule::exists('provinces', 'id')],
            'municipality_id' => ['required', 'integer', Rule::exists('municipalities', 'id')->where('province_id', $this->province_id)],
            'address_line' => ['required', 'string', 'max:255'],
        ]);
    }

    /**
     * Fills the coordinates from the geocoder. Returns false when nothing was found.
     *
     * @throws GeocodingUnavailable
     */
    public function geocodeAddress(Geocoder $geocoder): bool
    {
        $municipality = Municipality::query()->with('province')->find($this->municipality_id);

        $query = implode(', ', array_filter([
            trim($this->address_line),
            trim($this->postal_code.' '.($municipality->name ?? '')),
            $municipality === null ? '' : $municipality->province->name,
        ], fn (string $part): bool => $part !== ''));

        $result = $geocoder->geocode($query);

        if ($result === null) {
            return false;
        }

        $this->latitude = round($result->latitude, 7);
        $this->longitude = round($result->longitude, 7);
        $this->geocoding_source = GeocodingSource::Geocoder->value;
        $this->geocoding_provider = $result->provider;

        if (trim($this->postal_code) === '' && $result->postalCode !== null) {
            $this->postal_code = $result->postalCode;
        }

        return true;
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
