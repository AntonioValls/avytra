<?php

namespace App\Actions\Listings;

use App\Actions\Listings\Concerns\RecordsListingEvents;
use App\Enums\ListingEventType;
use App\Enums\ListingStatus;
use App\Exceptions\InvalidListingTransition;
use App\Exceptions\ListingNotPublishable;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\ListingPublished;
use App\Support\Audit\AuditLogger;
use App\Support\Listings\ListingPublishabilityValidator;
use App\Support\Listings\ListingSlugger;
use Illuminate\Support\Facades\DB;

/**
 * draft → published. Runs the "ready to publish" validation, fixes published_at the first
 * time only, starts the freshness clock, settles the slug and notifies the owner.
 */
class PublishListing
{
    use RecordsListingEvents;

    public function __construct(
        private AuditLogger $audit,
        private ListingPublishabilityValidator $validator,
        private ListingSlugger $slugger,
    ) {}

    public function handle(Listing $listing, User $actor): Listing
    {
        // Paused and expired listings return through ResumeListing, suspended ones through UnsuspendListing.
        throw_unless($listing->status === ListingStatus::Draft, InvalidListingTransition::for($listing, ListingStatus::Published));

        $report = $this->validator->validate($listing);

        throw_if($report->fails(), new ListingNotPublishable($report));

        return DB::transaction(function () use ($listing, $actor): Listing {
            $firstTime = ! $listing->hasBeenPublished();

            $this->transition($listing, ListingStatus::Published);

            if ($firstTime) {
                $listing->published_at = now();
                // The slug follows the final title until the listing is public for the first time (docs/15-seo.md).
                $listing->slug = $this->slugger->unique((string) $listing->title, $listing);
            } elseif ($listing->slug === null) {
                $listing->slug = $this->slugger->unique((string) $listing->title, $listing);
            }

            $this->restartFreshness($listing);
            $this->stampActor($listing, $actor);
            $listing->save();

            $this->recordEvent($listing, ListingEventType::Published, $actor, ['first_time' => $firstTime]);
            $this->auditIfOnBehalf($listing, 'listing.published_by_admin', $actor);

            if ($firstTime) {
                $listing->owner()->notify(new ListingPublished($listing));
            }

            return $listing;
        });
    }

    protected function audit(): AuditLogger
    {
        return $this->audit;
    }
}
