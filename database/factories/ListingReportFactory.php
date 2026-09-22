<?php

namespace Database\Factories;

use App\Enums\ListingReportReason;
use App\Enums\ListingReportStatus;
use App\Models\Listing;
use App\Models\ListingReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListingReport>
 */
class ListingReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * An open, anonymous report with a reply email, as a visitor without session sends it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory()->published(),
            'reporter_user_id' => null,
            'reporter_email' => fake()->safeEmail(),
            'reason' => ListingReportReason::MisleadingInformation,
            'message' => fake()->sentence(),
            'status' => ListingReportStatus::Open,
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'resolved_by_user_id' => null,
            'resolved_at' => null,
            'resolution_notes' => null,
        ];
    }

    public function forListing(Listing $listing): static
    {
        return $this->state(fn (array $attributes) => [
            'listing_id' => $listing->id,
        ]);
    }

    public function by(User $reporter): static
    {
        return $this->state(fn (array $attributes) => [
            'reporter_user_id' => $reporter->id,
            'reporter_email' => null,
        ]);
    }

    public function resolved(?User $by = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ListingReportStatus::Resolved,
            'resolved_by_user_id' => $by === null ? User::factory()->superadmin() : $by->id,
            'resolved_at' => now(),
            'resolution_notes' => 'Publicación pausada tras comprobar el aviso.',
        ]);
    }

    public function dismissed(?User $by = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ListingReportStatus::Dismissed,
            'resolved_by_user_id' => $by === null ? User::factory()->superadmin() : $by->id,
            'resolved_at' => now(),
            'resolution_notes' => null,
        ]);
    }
}
