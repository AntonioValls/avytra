<?php

namespace App\Policies;

use App\Models\User;

/**
 * Who may see or manage user accounts (docs/03-users-roles-permissions.md).
 *
 * Users only reach their own account (the settings pages). Listing, creating on behalf of
 * somebody and editing other people's accounts belong to the superadmin, who passes through
 * before(). Changing a role is not an ability at all: only the avytra:superadmin command does it.
 */
class UserPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($ability === 'changeRole') {
            return false;
        }

        return $user->isSuperadmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, User $target): bool
    {
        return $user->is($target);
    }

    /**
     * Creating an account on somebody's behalf (registration itself is not an ability).
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $target): bool
    {
        return $user->is($target);
    }

    /**
     * Sending the "set your password" link of an account created by the superadmin.
     */
    public function sendPasswordLink(User $user, User $target): bool
    {
        return false;
    }

    public function changeRole(User $user, User $target): bool
    {
        return false;
    }
}
