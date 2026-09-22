<?php

namespace Database\Factories;

use App\Enums\GeocodingSource;
use App\Enums\LocationVisibility;
use App\Models\Business;
use App\Models\Location;
use App\Models\Municipality;
use App\Models\Province;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The public_* columns are left null: they are derived by SaveBusinessLocation.
     * Tests that need them call the Action or set them explicitly.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'is_primary' => true,
            'country_code' => 'ES',
            'province_id' => Province::factory(),
            'municipality_id' => fn (array $attributes) => Municipality::factory()->state(['province_id' => $attributes['province_id']]),
            'postal_code' => fake()->postcode(),
            'address_line' => fake()->streetAddress(),
            'latitude' => null,
            'longitude' => null,
            'location_visibility' => LocationVisibility::Approximate,
            'public_latitude' => null,
            'public_longitude' => null,
            'public_radius_m' => null,
            'geocoding_source' => null,
            'geocoding_provider' => null,
            'geocoded_at' => null,
        ];
    }

    /**
     * A real point placed by the owner.
     */
    public function withCoordinates(float $latitude = 40.4167754, float $longitude = -3.7037902): static
    {
        return $this->state(fn (array $attributes) => [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'geocoding_source' => GeocodingSource::ManualPin,
        ]);
    }

    public function exact(): static
    {
        return $this->state(fn (array $attributes) => [
            'location_visibility' => LocationVisibility::Exact,
        ]);
    }

    public function approximate(): static
    {
        return $this->state(fn (array $attributes) => [
            'location_visibility' => LocationVisibility::Approximate,
        ]);
    }

    public function cityOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'location_visibility' => LocationVisibility::CityOnly,
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'location_visibility' => LocationVisibility::Hidden,
        ]);
    }
}
