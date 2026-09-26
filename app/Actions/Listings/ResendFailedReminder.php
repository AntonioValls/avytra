<?php

namespace App\Actions\Listings;

use App\Actions\Listings\Concerns\RecordsListingEvents;
use App\Enums\ListingEventType;
use App\Enums\ListingStatus;
use App\Enums\ReminderStage;
use App\Exceptions\InvalidListingTransition;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\ListingExpired;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * The superadmin resends, by hand, the reminder or pause email that could not be delivered
 * (docs/13, "Fallos y reintentos"). Which email depends on the failed event and on the
 * current status: a reminder for a published listing, the pause notice for an expired one.
 */
class ResendFailedReminder
{
    use RecordsListingEvents;

    public function __construct(private AuditLogger $audit, private SendListingFreshnessReminder $sendReminder) {}

    public function handle(Listing $listing, User $actor): Listing
    {
        $failed = $listing->latestFailedReminder();

        throw_if($failed === null, InvalidListingTransition::for($listing, $listing->status));

        $stage = ReminderStage::tryFrom((string) ($failed->payload['stage'] ?? ''));

        if ($stage !== null) {
            return $this->sendReminder->handle($listing, $stage, $actor);
        }

        throw_unless($listing->status === ListingStatus::Expired, InvalidListingTransition::for($listing, ListingStatus::Expired));

        return DB::transaction(function () use ($listing, $actor): Listing {
            $this->recordEvent($listing, ListingEventType::ReminderSent, $actor, ['stage' => 'expired', 'resent' => true]);
            $this->auditIfOnBehalf($listing, 'listing.reminder_resent_by_admin', $actor);

            $listing->owner()->notify(new ListingExpired($listing));

            return $listing;
        });
    }

    protected function audit(): AuditLogger
    {
        return $this->audit;
    }
}
