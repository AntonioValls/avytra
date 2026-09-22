<?php

namespace App\Actions\Businesses;

use App\Models\Business;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Updates a business and keeps its location / online profile coherent with its type:
 * a business that becomes online loses its premises; one that becomes physical loses
 * its online profile (docs/11-online-businesses.md). Audited when the actor is not the owner.
 */
class UpdateBusiness
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $attributes  fillable attributes of Business
     * @param  array<string, mixed>|null  $onlineProfile  fillable attributes of OnlineProfile; null keeps the existing one
     */
    public function handle(Business $business, User $actor, array $attributes, ?array $onlineProfile = null): Business
    {
        return DB::transaction(function () use ($business, $actor, $attributes, $onlineProfile): Business {
            $business->fill($attributes);
            $dirty = array_keys($business->getDirty());
            $before = Arr::only($business->getOriginal(), $dirty);
            $after = Arr::only($business->getAttributes(), $dirty);

            $business->updated_by_user_id = $actor->getKey();
            $business->save();

            if ($business->requiresOnlineProfile()) {
                if ($onlineProfile !== null) {
                    $business->onlineProfile()->updateOrCreate([], $onlineProfile);
                }
            } else {
                $business->onlineProfile()->delete();
            }

            if (! $business->requiresLocation()) {
                $business->location()->delete();
            }

            $business->unsetRelation('onlineProfile');
            $business->unsetRelation('location');

            if ($actor->isNot($business->owner) && $dirty !== []) {
                $this->audit->log(
                    action: 'business.updated_by_admin',
                    subject: $business,
                    actor: $actor,
                    onBehalfOf: $business->owner,
                    changes: ['before' => $this->scalars($before), 'after' => $this->scalars($after)],
                );
            }

            return $business;
        });
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function scalars(array $values): array
    {
        return array_map(fn (mixed $value): mixed => $value instanceof \BackedEnum ? $value->value : $value, $values);
    }
}
