<?php

namespace App\Actions\Users;

use App\Actions\Listings\ArchiveListing;
use App\Enums\ListingStatus;
use App\Models\Business;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a user account together with everything they own (ADR-017).
 *
 * Listings are archived first (so the history records the withdrawal) and then removed
 * with their business; locations, online profiles, metrics and events follow through
 * the database cascade. The businesses are removed for real (not soft-deleted) because
 * the account is gone. Phase 6 adds the images.
 */
class DeleteUserAccount
{
    public function __construct(private ArchiveListing $archiveListing) {}

    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->businesses()->withTrashed()->get()->each(function (Business $business): void {
                $business->listings()->withTrashed()->get()->each(function (Listing $listing): void {
                    if ($listing->status->canTransitionTo(ListingStatus::Archived)) {
                        $this->archiveListing->handle($listing, null);
                    }

                    $listing->forceDelete();
                });

                $business->forceDelete();
            });

            $user->delete();
        });
    }
}
