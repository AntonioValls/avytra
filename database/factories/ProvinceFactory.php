<?php

namespace Database\Factories;

use App\Models\Province;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Province>
 */
class ProvinceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'region_id' => Region::factory(),
            'country_code' => 'ES',
            'code' => str_pad((string) fake()->unique()->numberBetween(1, 999), 2, '0', STR_PAD_LEFT),
            'name' => $name,
            'slug' => Str::slug($name),
            'latitude' => fake()->latitude(36, 43),
            'longitude' => fake()->longitude(-9, 3),
        ];
    }
}
