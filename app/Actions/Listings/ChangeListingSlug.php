<?php

namespace App\Actions\Listings;

use App\Actions\Listings\Concerns\RecordsListingEvents;
use App\Enums\ListingEventType;
use App\Models\Listing;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Listings\ListingSlugger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Manual URL change (superadmin, ListingPolicy::changeSlug). The previous slug is kept
 * as a redirect so old links keep working and it is never reused. Always audited.
 */
class ChangeListingSlug
{
    use RecordsListingEvents;

    public function __construct(private AuditLogger $audit, private ListingSlugger $slugger) {}

    public function handle(Listing $listing, User $actor, string $newSlug): Listing
    {
        $newSlug = trim($newSlug);

        throw_if($newSlug === '' || $newSlug !== Str::slug($newSlug), new InvalidArgumentException('The slug must contain only lowercase letters, numbers and hyphens.'));

        if ($listing->slug === $newSlug) {
            return $listing;
        }

        throw_if($this->slugger->isTaken($newSlug, $listing), new InvalidArgumentException('That slug is already in use.'));

        return DB::transaction(function () use ($listing, $actor, $newSlug): Listing {
            $oldSlug = $listing->slug;

            if ($oldSlug !== null) {
                $listing->slugRedirects()->create(['old_slug' => $oldSlug]);
            }

            $listing->slug = $newSlug;
            $this->stampActor($listing, $actor);
            $listing->save();

            $this->recordEvent($listing, ListingEventType::SlugChanged, $actor, ['from' => $oldSlug, 'to' => $newSlug]);
            $this->audit->log(
                action: 'listing.slug_changed',
                subject: $listing,
                actor: $actor,
                onBehalfOf: $listing->owner(),
                changes: ['before' => ['slug' => $oldSlug], 'after' => ['slug' => $newSlug]],
            );

            return $listing;
        });
    }

    protected function audit(): AuditLogger
    {
        return $this->audit;
    }
}
