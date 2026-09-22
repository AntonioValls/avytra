<?php

namespace Database\Factories;

use App\Models\Municipality;
use App\Models\Province;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Municipality>
 */
class MunicipalityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->city().' '.fake()->unique()->numberBetween(1, 99999);

        return [
            'province_id' => Province::factory(),
            'country_code' => 'ES',
            'code' => str_pad((string) fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'name' => $name,
            'slug' => Str::slug($name),
            'latitude' => fake()->latitude(36, 43),
            'longitude' => fake()->longitude(-9, 3),
            'population' => fake()->numberBetween(100, 60000),
        ];
    }

    public function withPopulation(int $population): static
    {
        return $this->state(fn (array $attributes) => [
            'population' => $population,
        ]);
    }

    public function withoutCentre(): static
    {
        return $this->state(fn (array $attributes) => [
            'latitude' => null,
            'longitude' => null,
        ]);
    }
}
