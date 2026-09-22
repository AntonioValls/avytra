<?php

namespace App\Actions\Listings;

use App\Actions\Listings\Concerns\RecordsListingEvents;
use App\Enums\ListingStatus;
use App\Exceptions\InvalidListingTransition;
use App\Models\Listing;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Physical deletion, allowed only for drafts (docs/06-listing-lifecycle.md).
 * Everything else is archived, never deleted.
 */
class DeleteListingDraft
{
    use RecordsListingEvents;

    public function __construct(private AuditLogger $audit) {}

    public function handle(Listing $listing, User $actor): void
    {
        throw_unless($listing->status === ListingStatus::Draft, InvalidListingTransition::for($listing, ListingStatus::Archived));

        DB::transaction(function () use ($listing, $actor): void {
            $this->auditIfOnBehalf($listing, 'listing.draft_deleted_by_admin', $actor, [
                'before' => ['title' => $listing->title, 'business_id' => $listing->business_id],
            ]);

            $listing->forceDelete();
        });
    }

    protected function audit(): AuditLogger
    {
        return $this->audit;
    }
}
