<?php

namespace App\Enums;

/**
 * Lifecycle of a listing (docs/06-listing-lifecycle.md). The transition table lives here;
 * the Actions in App\Actions\Listings enforce it and the Policies decide who may trigger it.
 */
enum ListingStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Paused = 'paused';
    case Expired = 'expired';
    case Sold = 'sold';
    case Archived = 'archived';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Published => __('Published'),
            self::Paused => __('Paused'),
            self::Expired => __('Paused for lack of confirmation'),
            self::Sold => __('Sold'),
            self::Archived => __('Archived'),
            self::Suspended => __('Suspended'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Draft => __('Not published yet. Finish the steps whenever you want.'),
            self::Published => __('Visible to buyers.'),
            self::Paused => __('Paused by you. Nobody can see it until you resume it.'),
            self::Expired => __('We could not confirm it was still available, so it is hidden. Resume it with one click.'),
            self::Sold => __('The business changed hands. Congratulations.'),
            self::Archived => __('Withdrawn. Publish the business again whenever you want.'),
            self::Suspended => __('Withdrawn by AVYTRA. Contact us if you think this is a mistake.'),
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft, self::Paused, self::Archived => 'zinc',
            self::Published => 'lime',
            self::Expired => 'amber',
            self::Sold => 'blue',
            self::Suspended => 'red',
        };
    }

    /**
     * Sold and archived listings never change again; the business is free for a new listing.
     */
    public function isTerminal(): bool
    {
        return $this === self::Sold || $this === self::Archived;
    }

    /**
     * States that may appear in public output (sold only within sold_visible_days, checked on the model).
     */
    public function isPubliclyVisible(): bool
    {
        return $this === self::Published || $this === self::Sold;
    }

    /**
     * The owner may edit the content unless the listing was archived or withdrawn by moderation.
     */
    public function isEditableByOwner(): bool
    {
        return $this !== self::Archived && $this !== self::Suspended;
    }

    /**
     * @return list<ListingStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Published, self::Archived],
            self::Published => [self::Paused, self::Expired, self::Sold, self::Archived, self::Suspended],
            self::Paused => [self::Published, self::Sold, self::Archived, self::Suspended],
            self::Expired => [self::Published, self::Sold, self::Archived, self::Suspended],
            self::Sold => [self::Archived],
            self::Suspended => [self::Published, self::Archived],
            self::Archived => [],
        };
    }

    public function canTransitionTo(ListingStatus $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /**
     * @return list<ListingStatus>
     */
    public static function nonTerminal(): array
    {
        return array_values(array_filter(self::cases(), fn (ListingStatus $status): bool => ! $status->isTerminal()));
    }
}
