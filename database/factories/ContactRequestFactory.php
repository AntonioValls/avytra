<?php

namespace Database\Factories;

use App\Models\ContactRequest;
use App\Models\Listing;
use App\Models\User;
use App\Support\Security\IpHash;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactRequest>
 */
class ContactRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * An unread, delivered message from an anonymous visitor.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory()->published(),
            'sender_user_id' => null,
            'sender_name' => fake()->name(),
            'sender_email' => fake()->safeEmail(),
            'sender_phone' => null,
            'message' => fake()->paragraph(),
            'ip_hash' => IpHash::make(fake()->ipv4()),
            'read_at' => null,
            'delivery_failed_at' => null,
            'delivery_error' => null,
        ];
    }

    public function forListing(Listing $listing): static
    {
        return $this->state(fn (array $attributes) => [
            'listing_id' => $listing->id,
        ]);
    }

    public function from(User $sender): static
    {
        return $this->state(fn (array $attributes) => [
            'sender_user_id' => $sender->id,
            'sender_name' => $sender->name,
            'sender_email' => $sender->email,
        ]);
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => now(),
        ]);
    }

    public function undelivered(string $error = 'Connection refused'): static
    {
        return $this->state(fn (array $attributes) => [
            'delivery_failed_at' => now(),
            'delivery_error' => $error,
        ]);
    }
}
