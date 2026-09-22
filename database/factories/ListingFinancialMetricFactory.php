<?php

namespace Database\Factories;

use App\Enums\Disclosure;
use App\Enums\FinancialMetric;
use App\Models\Listing;
use App\Models\ListingFinancialMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListingFinancialMetric>
 */
class ListingFinancialMetricFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'metric' => FinancialMetric::AnnualRevenue,
            'disclosure' => Disclosure::Exact,
            'amount' => fake()->numberBetween(50000, 2000000),
            'amount_min' => null,
            'amount_max' => null,
            'currency' => 'EUR',
            'period_year' => (int) now()->subYear()->year,
        ];
    }

    public function metric(FinancialMetric $metric): static
    {
        return $this->state(fn (array $attributes) => [
            'metric' => $metric,
        ]);
    }

    public function range(int $min, int $max): static
    {
        return $this->state(fn (array $attributes) => [
            'disclosure' => Disclosure::Range,
            'amount' => null,
            'amount_min' => $min,
            'amount_max' => $max,
        ]);
    }

    public function onRequest(): static
    {
        return $this->state(fn (array $attributes) => [
            'disclosure' => Disclosure::OnRequest,
            'amount' => null,
            'amount_min' => null,
            'amount_max' => null,
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'disclosure' => Disclosure::Hidden,
        ]);
    }
}
