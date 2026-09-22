<?php

namespace App\Actions\Businesses;

use App\Models\Business;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Moves a business (and, through it, its listings) to another user.
 * Superadmin only (BusinessPolicy::transferOwnership); always audited.
 */
class TransferBusinessOwnership
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Business $business, User $newOwner, User $actor): Business
    {
        if ($business->isOwnedBy($newOwner)) {
            return $business;
        }

        return DB::transaction(function () use ($business, $newOwner, $actor): Business {
            $previousOwnerId = $business->owner_user_id;

            $business->owner()->associate($newOwner);
            $business->updated_by_user_id = $actor->getKey();
            $business->save();

            $this->audit->log(
                action: 'business.owner_changed',
                subject: $business,
                actor: $actor,
                onBehalfOf: $newOwner,
                changes: [
                    'before' => ['owner_user_id' => $previousOwnerId],
                    'after' => ['owner_user_id' => $newOwner->getKey()],
                ],
            );

            return $business;
        });
    }
}
