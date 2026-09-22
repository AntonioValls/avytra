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
 * suspended → published. The freshness clock restarts as if freshly published. Always audited.
 */
class UnsuspendListing
{
    use RecordsListingEvents;

    public function __construct(private AuditLogger $audit) {}

    public function handle(Listing $listing, User $actor): Listing
    {
        throw_unless($listing->status === ListingStatus::Suspended, InvalidListingTransition::for($listing, ListingStatus::Published));

        return DB::transaction(function () use ($listing, $actor): Listing {
            $reason = $listing->suspension_reason;

            $this->transition($listing, ListingStatus::Published);
            $listing->suspended_at = null;
            $listing->suspension_reason = null;
            $this->restartFreshness($listing);
            $this->stampActor($listing, $actor);
            $listing->save();

            $this->recordEvent($listing, ListingEventType::Unsuspended, $actor);
            $this->audit->log(
                action: 'listing.unsuspended',
                subject: $listing,
                actor: $actor,
                onBehalfOf: $listing->owner(),
                changes: ['before' => ['suspension_reason' => $reason]],
            );

            return $listing;
        });
    }

    protected function audit(): AuditLogger
    {
        return $this->audit;
    }
}
