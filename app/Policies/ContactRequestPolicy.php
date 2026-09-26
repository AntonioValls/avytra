<?php

namespace App\Policies;

use App\Models\ContactRequest;
use App\Models\User;

/**
 * Messages sent through the relay form belong to the owner of the listing (docs/12,
 * ADR-019). Sending one is open to everybody, including guests, so it is not an ability
 * here: the rate limits and the honeypot guard it instead.
 */
class ContactRequestPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperadmin() ? true : null;
    }

    /**
     * Any user may open the messages page; the query itself is scoped to their listings.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ContactRequest $request): bool
    {
        return $request->listing->isOwnedBy($user);
    }

    public function markAsRead(User $user, ContactRequest $request): bool
    {
        return $request->listing->isOwnedBy($user);
    }
}
