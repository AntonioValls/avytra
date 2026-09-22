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
 * paused|expired → published. Counts as a confirmation: the freshness clock restarts.
 * published_at is untouched so "recent" ordering and SEO stay stable.
 */
class ResumeListing
{
    use RecordsListingEvents;

    public function __construct(private AuditLogger $audit) {}

    public function handle(Listing $listing, User $actor, string $channel = 'dashboard'): Listing
    {
        throw_unless(
            in_array($listing->status, [ListingStatus::Paused, ListingStatus::Expired], true),
            InvalidListingTransition::for($listing, ListingStatus::Published),
        );

        return DB::transaction(function () use ($listing, $actor, $channel): Listing {
            $this->transition($listing, ListingStatus::Published);
            $listing->paused_at = null;
            $listing->expired_at = null;
            $this->restartFreshness($listing);
            $this->stampActor($listing, $actor);
            $listing->save();

            $this->recordEvent($listing, ListingEventType::Resumed, $actor, ['channel' => $channel]);
            $this->auditIfOnBehalf($listing, 'listing.resumed_by_admin', $actor);

            return $listing;
        });
    }

    protected function audit(): AuditLogger
    {
        return $this->audit;
    }
}
