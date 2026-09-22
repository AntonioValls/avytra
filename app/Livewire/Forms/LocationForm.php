<?php

namespace App\Livewire\Forms;

use App\Enums\LocationVisibility;
use App\Models\Location;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Premises of a physical or hybrid business. Coordinates are accepted but the map
 * picker that fills them arrives in Phase 5; until then the municipality centre is used.
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
        ];
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
