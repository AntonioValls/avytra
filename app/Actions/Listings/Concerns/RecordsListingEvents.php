<?php

namespace App\Actions\Listings\Concerns;

use App\Enums\ListingEventType;
use App\Enums\ListingStatus;
use App\Exceptions\InvalidListingTransition;
use App\Models\Listing;
use App\Models\ListingEvent;
use App\Models\User;
use App\Support\Audit\AuditLogger;

/**
 * Shared plumbing of the listing Actions: status change guarded by the transition table,
 * history entry in listing_events and audit entry when the actor is not the owner.
 * Using Actions expose an AuditLogger through $this->audit.
 */
trait RecordsListingEvents
{
    abstract protected function audit(): AuditLogger;

    /**
     * Moves the listing to a new status or throws when the table forbids it.
     */
    protected function transition(Listing $listing, ListingStatus $to): void
    {
        throw_unless($listing->status->canTransitionTo($to), InvalidListingTransition::for($listing, $to));

        $listing->status = $to;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function recordEvent(Listing $listing, ListingEventType $type, ?User $actor, array $payload = []): ListingEvent
    {
        return $listing->events()->create([
            'type' => $type,
            'actor_user_id' => $actor?->getKey(),
            'on_behalf_of_user_id' => $this->onBehalfOf($listing, $actor)?->getKey(),
            'payload' => $payload === [] ? null : $payload,
        ]);
    }

    /**
     * Audits the action when somebody other than the owner (the superadmin) performs it.
     *
     * @param  array{before?: array<string, mixed>, after?: array<string, mixed>}|null  $changes
     */
    protected function auditIfOnBehalf(Listing $listing, string $action, ?User $actor, ?array $changes = null): void
    {
        $owner = $this->onBehalfOf($listing, $actor);

        if ($owner === null) {
            return;
        }

        $this->audit()->log(action: $action, subject: $listing, actor: $actor, onBehalfOf: $owner, changes: $changes);
    }

    /**
     * The owner, when the actor is somebody else; null when the owner acts for themselves.
     */
    protected function onBehalfOf(Listing $listing, ?User $actor): ?User
    {
        $owner = $listing->owner();

        return $actor === null || $actor->is($owner) ? null : $owner;
    }

    protected function stampActor(Listing $listing, ?User $actor): void
    {
        if ($actor !== null) {
            $listing->updated_by_user_id = $actor->getKey();
        }
    }

    /**
     * Starts (or restarts) the freshness clock as if the listing were freshly published.
     */
    protected function restartFreshness(Listing $listing): void
    {
        $now = now();

        $listing->last_confirmed_at = $now;
        $listing->next_confirmation_at = $now->addDays((int) config('avytra.freshness.confirmation_period_days'));
        $listing->first_reminder_sent_at = null;
        $listing->second_reminder_sent_at = null;
    }
}
