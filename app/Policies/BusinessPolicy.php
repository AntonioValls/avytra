<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;

/**
 * Who may do what with a business (docs/03-users-roles-permissions.md).
 *
 * Permissions derive from ownership. The superadmin passes every check through
 * before(); abilities that only the superadmin holds therefore return false here.
 */
class BusinessPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperadmin() ? true : null;
    }

    /**
     * Any user may list businesses; the query itself is scoped to their own.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Business $business): bool
    {
        return $business->isOwnedBy($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Business $business): bool
    {
        return $business->isOwnedBy($user);
    }

    /**
     * Owners may delete their businesses. Phase 3 adds the restriction
     * "only when no listing has been published or sold".
     */
    public function delete(User $user, Business $business): bool
    {
        return $business->isOwnedBy($user);
    }

    public function transferOwnership(User $user, Business $business): bool
    {
        return false;
    }

    public function restore(User $user, Business $business): bool
    {
        return false;
    }

    public function forceDelete(User $user, Business $business): bool
    {
        return false;
    }
}
