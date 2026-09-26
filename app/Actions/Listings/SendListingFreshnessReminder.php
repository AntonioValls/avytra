<?php

namespace App\Actions\Listings;

use App\Actions\Listings\Concerns\RecordsListingEvents;
use App\Enums\ListingEventType;
use App\Enums\ListingStatus;
use App\Enums\ReminderStage;
use App\Exceptions\InvalidListingTransition;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\ListingFreshnessReminder;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Sends one availability reminder to the owner of a published listing. The sent_at flag of
 * the stage is written before the notification is queued, so a retried job or an overlapping
 * command run never sends it twice (docs/13). The superadmin may resend a failed one.
 */
class SendListingFreshnessReminder
{
    use RecordsListingEvents;

    public function __construct(private AuditLogger $audit) {}

    public function handle(Listing $listing, ReminderStage $stage, ?User $actor = null): Listing
    {
        throw_unless($listing->status === ListingStatus::Published, InvalidListingTransition::for($listing, ListingStatus::Published));

        return DB::transaction(function () use ($listing, $stage, $actor): Listing {
            $now = now();

            // A second reminder counts as the first one too: only one email per run (docs/13).
            $listing->first_reminder_sent_at ??= $now;

            if ($stage === ReminderStage::Second) {
                $listing->second_reminder_sent_at = $now;
            }

            $listing->save();

            $this->recordEvent($listing, ListingEventType::ReminderSent, $actor, [
                'stage' => $stage->value,
                'resent' => $actor !== null,
            ]);
            $this->auditIfOnBehalf($listing, 'listing.reminder_resent_by_admin', $actor);

            $listing->owner()->notify(new ListingFreshnessReminder($listing, $stage));

            return $listing;
        });
    }

    protected function audit(): AuditLogger
    {
        return $this->audit;
    }
}
