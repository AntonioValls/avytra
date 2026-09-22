<?php

namespace App\Policies;

use App\Enums\ListingStatus;
use App\Models\Business;
use App\Models\Listing;
use App\Models\User;

/**
 * Who may do what with a listing (docs/03-users-roles-permissions.md).
 *
 * Ownership comes from the business. The Policy decides who; the Actions decide
 * whether the current status allows the change. The superadmin passes every check
 * through before(), so abilities reserved to them return false here.
 */
class ListingPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperadmin() ? true : null;
    }

    /**
     * Any user may list listings; the query itself is scoped to their own.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Listing $listing): bool
    {
        return $listing->isOwnedBy($user);
    }

    /**
     * Creating a listing is an action on a business: only its owner may.
     */
    public function create(User $user, Business $business): bool
    {
        return $business->isOwnedBy($user);
    }

    public function update(User $user, Listing $listing): bool
    {
        return $listing->isOwnedBy($user) && $listing->status->isEditableByOwner();
    }

    public function publish(User $user, Listing $listing): bool
    {
        return $listing->isOwnedBy($user);
    }

    public function pause(User $user, Listing $listing): bool
    {
        return $listing->isOwnedBy($user);
    }

    public function resume(User $user, Listing $listing): bool
    {
        return $listing->isOwnedBy($user);
    }

    public function confirm(User $user, Listing $listing): bool
    {
        return $listing->isOwnedBy($user);
    }

    public function markSold(User $user, Listing $listing): bool
    {
        return $listing->isOwnedBy($user);
    }

    public function archive(User $user, Listing $listing): bool
    {
        return $listing->isOwnedBy($user);
    }

    /**
     * Physical deletion exists only for drafts.
     */
    public function delete(User $user, Listing $listing): bool
    {
        return $listing->isOwnedBy($user) && $listing->status === ListingStatus::Draft;
    }

    public function suspend(User $user, Listing $listing): bool
    {
        return false;
    }

    public function unsuspend(User $user, Listing $listing): bool
    {
        return false;
    }

    public function changeSlug(User $user, Listing $listing): bool
    {
        return false;
    }

    public function restore(User $user, Listing $listing): bool
    {
        return false;
    }

    public function forceDelete(User $user, Listing $listing): bool
    {
        return false;
    }
}
