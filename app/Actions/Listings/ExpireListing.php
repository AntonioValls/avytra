<?php

namespace App\Actions\Listings;

use App\Actions\Listings\Concerns\RecordsListingEvents;
use App\Enums\ListingEventType;
use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * published → expired. Run by the system (no actor) when availability was not confirmed
 * in time. Data intact; the owner reactivates with one click. The ListingExpired
 * notification is sent by the freshness command (Phase 7).
 */
class ExpireListing
{
    use RecordsListingEvents;

    public function __construct(private AuditLogger $audit) {}

    public function handle(Listing $listing): Listing
    {
        return DB::transaction(function () use ($listing): Listing {
            $this->transition($listing, ListingStatus::Expired);
            $listing->expired_at = now();
            $listing->save();

            $this->recordEvent($listing, ListingEventType::Expired, null);

            return $listing;
        });
    }

    protected function audit(): AuditLogger
    {
        return $this->audit;
    }
}
