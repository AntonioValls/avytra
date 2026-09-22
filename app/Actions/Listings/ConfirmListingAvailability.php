<?php

namespace App\Actions\Listings;

use App\Actions\Listings\Concerns\RecordsListingEvents;
use App\Enums\ListingEventType;
use App\Enums\ListingStatus;
use App\Exceptions\InvalidListingTransition;
use App\Models\Listing;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * "Still available": a published listing stays published and its freshness clock restarts.
 * The channel (dashboard, email_link, admin) is kept in the event payload (ADR-007).
 */
class ConfirmListingAvailability
{
    use RecordsListingEvents;

    public function __construct(private AuditLogger $audit) {}

    public function handle(Listing $listing, User $actor, string $channel = 'dashboard'): Listing
    {
        throw_unless($listing->status === ListingStatus::Published, InvalidListingTransition::for($listing, ListingStatus::Published));

        return DB::transaction(function () use ($listing, $actor, $channel): Listing {
            $this->restartFreshness($listing);
            $this->stampActor($listing, $actor);
            $listing->save();

            $this->recordEvent($listing, ListingEventType::Confirmed, $actor, ['channel' => $channel]);
            $this->auditIfOnBehalf($listing, 'listing.confirmed_by_admin', $actor);

            return $listing;
        });
    }

    protected function audit(): AuditLogger
    {
        return $this->audit;
    }
}
