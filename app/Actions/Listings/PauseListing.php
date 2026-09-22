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
 * published → paused. Voluntary pause; data intact.
 */
class PauseListing
{
    use RecordsListingEvents;

    public function __construct(private AuditLogger $audit) {}

    public function handle(Listing $listing, User $actor): Listing
    {
        return DB::transaction(function () use ($listing, $actor): Listing {
            $this->transition($listing, ListingStatus::Paused);
            $listing->paused_at = now();
            $this->stampActor($listing, $actor);
            $listing->save();

            $this->recordEvent($listing, ListingEventType::Paused, $actor);
            $this->auditIfOnBehalf($listing, 'listing.paused_by_admin', $actor);

            return $listing;
        });
    }

    protected function audit(): AuditLogger
    {
        return $this->audit;
    }
}
