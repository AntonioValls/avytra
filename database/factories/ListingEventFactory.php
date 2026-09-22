<?php

namespace Database\Factories;

use App\Enums\ListingEventType;
use App\Models\Listing;
use App\Models\ListingEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListingEvent>
 */
class ListingEventFactory extends Factory
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
            'type' => ListingEventType::Published,
            'actor_user_id' => null,
            'on_behalf_of_user_id' => null,
            'payload' => null,
        ];
    }

    public function type(ListingEventType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
        ]);
    }
}
