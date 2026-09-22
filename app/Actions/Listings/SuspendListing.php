<?php

namespace App\Actions\Listings;

use App\Actions\Listings\Concerns\RecordsListingEvents;
use App\Enums\ListingEventType;
use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\ListingSuspended;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * published|paused|expired → suspended. Moderation by the superadmin (ADR-011):
 * always audited, the owner is notified with the reason.
 */
class SuspendListing
{
    use RecordsListingEvents;

    public function __construct(private AuditLogger $audit) {}

    public function handle(Listing $listing, User $actor, string $reason): Listing
    {
        return DB::transaction(function () use ($listing, $actor, $reason): Listing {
            $this->transition($listing, ListingStatus::Suspended);
            $listing->suspended_at = now();
            $listing->suspension_reason = $reason;
            $this->stampActor($listing, $actor);
            $listing->save();

            $this->recordEvent($listing, ListingEventType::Suspended, $actor, ['reason' => $reason]);
            $this->audit->log(
                action: 'listing.suspended',
                subject: $listing,
                actor: $actor,
                onBehalfOf: $listing->owner(),
                changes: ['after' => ['suspension_reason' => $reason]],
            );

            $listing->owner()->notify(new ListingSuspended($listing));

            return $listing;
        });
    }

    protected function audit(): AuditLogger
    {
        return $this->audit;
    }
}
