<?php

namespace Database\Factories;

use App\Enums\BusinessType;
use App\Enums\EmployeeRange;
use App\Enums\LegalForm;
use App\Enums\WebsiteVisibility;
use App\Models\Business;
use App\Models\Category;
use App\Models\Location;
use App\Models\OnlineProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_user_id' => User::factory(),
            'created_by_user_id' => null,
            'updated_by_user_id' => null,
            'business_type' => BusinessType::Physical,
            'category_id' => Category::factory(),
            'subcategory_id' => null,
            'name' => fake()->company(),
            'legal_name' => null,
            'legal_form' => fake()->randomElement(LegalForm::cases()),
            'show_legal_form' => false,
            'tagline' => fake()->sentence(8),
            'description' => fake()->paragraphs(3, true),
            'founded_year' => fake()->numberBetween(1990, (int) now()->year),
            'employee_range' => fake()->randomElement(EmployeeRange::cases()),
            'website_url' => null,
            'website_visibility' => WebsiteVisibility::Private,
        ];
    }

    /**
     * Sets the owner. The creator is left null unless createdBy() is used.
     */
    public function ownedBy(User $owner): static
    {
        return $this->state(fn (array $attributes) => [
            'owner_user_id' => $owner->id,
        ]);
    }

    public function createdBy(User $creator): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by_user_id' => $creator->id,
            'updated_by_user_id' => $creator->id,
        ]);
    }

    public function physical(): static
    {
        return $this->state(fn (array $attributes) => [
            'business_type' => BusinessType::Physical,
        ]);
    }

    public function online(): static
    {
        return $this->state(fn (array $attributes) => [
            'business_type' => BusinessType::Online,
        ]);
    }

    public function hybrid(): static
    {
        return $this->state(fn (array $attributes) => [
            'business_type' => BusinessType::Hybrid,
        ]);
    }

    /**
     * Adds a primary location (physical and hybrid businesses).
     */
    public function withLocation(?LocationFactory $location = null): static
    {
        return $this->has($location ?? Location::factory(), 'location');
    }

    /**
     * Adds an online profile (online and hybrid businesses).
     */
    public function withOnlineProfile(?OnlineProfileFactory $profile = null): static
    {
        return $this->has($profile ?? OnlineProfile::factory(), 'onlineProfile');
    }
}
