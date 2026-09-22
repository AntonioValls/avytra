<?php

namespace App\Actions\Listings;

use App\Actions\Listings\Concerns\RecordsListingEvents;
use App\Enums\ListingEventType;
use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * any non-archived → archived. Terminal; nothing is deleted.
 * The actor is null when the system archives (e.g. the account is being deleted).
 */
class ArchiveListing
{
    use RecordsListingEvents;

    public function __construct(private AuditLogger $audit) {}

    public function handle(Listing $listing, ?User $actor): Listing
    {
        return DB::transaction(function () use ($listing, $actor): Listing {
            $this->transition($listing, ListingStatus::Archived);
            $listing->archived_at = now();
            $this->stampActor($listing, $actor);
            $listing->save();

            $this->recordEvent($listing, ListingEventType::Archived, $actor);
            $this->auditIfOnBehalf($listing, 'listing.archived_by_admin', $actor);

            return $listing;
        });
    }

    protected function audit(): AuditLogger
    {
        return $this->audit;
    }
}
