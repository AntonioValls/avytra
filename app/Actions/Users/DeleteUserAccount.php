<?php

namespace App\Actions\Users;

use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a user account together with everything they own (ADR-017).
 *
 * The businesses are removed for real (not soft-deleted) because the account is gone;
 * locations and online profiles follow through the database cascade. Phase 3 archives
 * the listings and anonymises their contact data before this runs.
 */
class DeleteUserAccount
{
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->businesses()->withTrashed()->get()->each(fn (Business $business) => $business->forceDelete());

            $user->delete();
        });
    }
}
