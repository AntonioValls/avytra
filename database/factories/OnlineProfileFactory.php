<?php

namespace Database\Factories;

use App\Enums\AcquisitionChannel;
use App\Enums\Disclosure;
use App\Enums\LogisticsType;
use App\Enums\OnlineBusinessType;
use App\Enums\TechnologyPlatform;
use App\Models\Business;
use App\Models\OnlineProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnlineProfile>
 */
class OnlineProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory()->online(),
            'online_business_type' => OnlineBusinessType::Ecommerce,
            'technology_platform' => TechnologyPlatform::Shopify,
            'technology_platform_other' => null,
            'domain_registered_year' => fake()->numberBetween(2005, (int) now()->year),
            'monthly_visits' => fake()->numberBetween(500, 200000),
            'monthly_visits_disclosure' => Disclosure::Exact,
            'registered_users' => null,
            'active_customers' => null,
            'monthly_orders' => fake()->numberBetween(10, 5000),
            'recurring_revenue_percent' => null,
            'acquisition_channels' => [AcquisitionChannel::Seo->value, AcquisitionChannel::SocialAds->value],
            'social_profiles' => null,
            'sells_on_marketplaces' => null,
            'has_stock' => true,
            'logistics_type' => LogisticsType::Outsourced,
            'team_included' => null,
        ];
    }

    public function saas(): static
    {
        return $this->state(fn (array $attributes) => [
            'online_business_type' => OnlineBusinessType::Saas,
            'technology_platform' => TechnologyPlatform::LaravelCustom,
            'monthly_orders' => null,
            'recurring_revenue_percent' => 90,
            'has_stock' => false,
            'logistics_type' => LogisticsType::None,
        ]);
    }
}
