<?php

namespace App\Policies;

use App\Models\ListingReport;
use App\Models\User;

/**
 * Reports are read and resolved only by the superadmin (docs/03-users-roles-permissions.md).
 * Submitting one is open to everybody, including guests, so it is not an ability here:
 * the rate limit and the honeypot guard it instead.
 */
class ListingReportPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperadmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, ListingReport $report): bool
    {
        return false;
    }

    public function resolve(User $user, ListingReport $report): bool
    {
        return false;
    }
}
