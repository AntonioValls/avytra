<?php

namespace Database\Factories;

use App\Enums\ContactMethod;
use App\Enums\ListingStatus;
use App\Enums\OperationType;
use App\Enums\PriceDisclosure;
use App\Models\Business;
use App\Models\Listing;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * A complete draft: every field required to publish is filled in, so tests only
     * remove what they want to see fail. Lifecycle timestamps come from the state methods.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = 'Traspaso de '.fake()->word().' '.fake()->word().' '.fake()->unique()->numberBetween(1, 99999);

        return [
            'business_id' => Business::factory(),
            'created_by_user_id' => null,
            'updated_by_user_id' => null,
            'status' => ListingStatus::Draft,
            'primary_operation_type' => OperationType::Transfer,
            'stake_percent' => null,
            'operation_notes' => null,
            'title' => $title,
            'slug' => Str::slug($title),
            'reason_for_sale' => fake()->sentence(),
            'highlights' => null,
            'includes_stock' => null,
            'includes_equipment' => null,
            'includes_property' => null,
            'includes_staff' => null,
            'includes_intellectual_property' => null,
            'included_assets_notes' => null,
            'premises_is_rented' => null,
            'price_disclosure' => PriceDisclosure::Exact,
            'asking_price' => fake()->numberBetween(20000, 900000),
            'asking_price_min' => null,
            'asking_price_max' => null,
            'is_price_negotiable' => null,
            'currency' => 'EUR',
            'contact_name' => fake()->firstName(),
            'preferred_contact_method' => ContactMethod::Email,
            'contact_email' => fake()->safeEmail(),
            'contact_phone' => null,
            'contact_whatsapp' => null,
            'contact_website_url' => null,
            'contact_form_url' => null,
            'contact_other' => null,
            'contact_notes' => null,
            'published_at' => null,
            'last_confirmed_at' => null,
            'next_confirmation_at' => null,
            'first_reminder_sent_at' => null,
            'second_reminder_sent_at' => null,
            'paused_at' => null,
            'expired_at' => null,
            'sold_at' => null,
            'archived_at' => null,
            'suspended_at' => null,
            'suspension_reason' => null,
        ];
    }

    /**
     * Every listing offers at least its primary operation type.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Listing $listing): void {
            if ($listing->primary_operation_type !== null && $listing->operationTypes()->doesntExist()) {
                $listing->operationTypes()->create(['operation_type' => $listing->primary_operation_type]);
                $listing->unsetRelation('operationTypes');
            }
        });
    }

    public function forBusiness(Business $business): static
    {
        return $this->state(fn (array $attributes) => [
            'business_id' => $business->id,
        ]);
    }

    public function createdBy(User $creator): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by_user_id' => $creator->id,
            'updated_by_user_id' => $creator->id,
        ]);
    }

    /**
     * @param  list<OperationType>  $types
     */
    public function offering(array $types, ?OperationType $primary = null): static
    {
        return $this
            ->state(fn (array $attributes) => ['primary_operation_type' => $primary ?? $types[0]])
            ->afterCreating(function (Listing $listing) use ($types): void {
                foreach ($types as $type) {
                    $listing->operationTypes()->firstOrCreate(['operation_type' => $type->value]);
                }
                $listing->unsetRelation('operationTypes');
            });
    }

    /**
     * A bare draft as the wizard creates it in step 1: nothing but the operation.
     */
    public function bare(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => null,
            'slug' => null,
            'reason_for_sale' => null,
            'price_disclosure' => PriceDisclosure::OnRequest,
            'asking_price' => null,
            'contact_name' => null,
            'preferred_contact_method' => null,
            'contact_email' => null,
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ListingStatus::Draft,
        ]);
    }

    /**
     * Published and confirmed right now (or at the given moment).
     */
    public function published(?\DateTimeInterface $at = null): static
    {
        $at = $at === null ? now() : CarbonImmutable::instance($at);

        return $this->state(fn (array $attributes) => [
            'status' => ListingStatus::Published,
            'published_at' => $at,
            'last_confirmed_at' => $at,
            'next_confirmation_at' => $at->addDays((int) config('avytra.freshness.confirmation_period_days')),
        ]);
    }

    /**
     * Published long enough ago to show the "needs confirmation" badge.
     */
    public function needingConfirmation(): static
    {
        return $this->published(now()->subDays((int) config('avytra.freshness.first_reminder_days') + 1));
    }

    public function paused(): static
    {
        return $this->published(now()->subDays(10))->state(fn (array $attributes) => [
            'status' => ListingStatus::Paused,
            'paused_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->published(now()->subDays((int) config('avytra.freshness.confirmation_period_days') + 1))->state(fn (array $attributes) => [
            'status' => ListingStatus::Expired,
            'expired_at' => now(),
        ]);
    }

    public function sold(?\DateTimeInterface $at = null): static
    {
        return $this->published(now()->subDays(30))->state(fn (array $attributes) => [
            'status' => ListingStatus::Sold,
            'sold_at' => $at ?? now(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ListingStatus::Archived,
            'archived_at' => now(),
        ]);
    }

    public function suspended(string $reason = 'Contenido inadecuado'): static
    {
        return $this->published(now()->subDays(5))->state(fn (array $attributes) => [
            'status' => ListingStatus::Suspended,
            'suspended_at' => now(),
            'suspension_reason' => $reason,
        ]);
    }

    public function priceOnRequest(): static
    {
        return $this->state(fn (array $attributes) => [
            'price_disclosure' => PriceDisclosure::OnRequest,
            'asking_price' => null,
            'asking_price_min' => null,
            'asking_price_max' => null,
        ]);
    }

    public function priceRange(int $min, int $max): static
    {
        return $this->state(fn (array $attributes) => [
            'price_disclosure' => PriceDisclosure::Range,
            'asking_price' => null,
            'asking_price_min' => $min,
            'asking_price_max' => $max,
        ]);
    }
}
