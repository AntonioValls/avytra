<?php

namespace App\Actions\Businesses;

use App\Models\Business;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates a business for an owner. The actor may be the owner or the superadmin
 * acting on their behalf; in the latter case the action is audited.
 */
class CreateBusiness
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $attributes  fillable attributes of Business
     * @param  array<string, mixed>|null  $onlineProfile  fillable attributes of OnlineProfile (online/hybrid only)
     */
    public function handle(User $owner, User $actor, array $attributes, ?array $onlineProfile = null): Business
    {
        return DB::transaction(function () use ($owner, $actor, $attributes, $onlineProfile): Business {
            $business = new Business($attributes);
            $business->owner()->associate($owner);
            $business->created_by_user_id = $actor->getKey();
            $business->updated_by_user_id = $actor->getKey();
            $business->save();

            if ($onlineProfile !== null) {
                throw_unless(
                    $business->requiresOnlineProfile(),
                    new InvalidArgumentException('A physical business cannot have an online profile.'),
                );

                $business->onlineProfile()->create($onlineProfile);
            }

            if ($actor->isNot($owner)) {
                $this->audit->log(
                    action: 'business.created_by_admin',
                    subject: $business,
                    actor: $actor,
                    onBehalfOf: $owner,
                    changes: ['after' => ['name' => $business->name, 'business_type' => $business->business_type->value]],
                );
            }

            return $business;
        });
    }
}
