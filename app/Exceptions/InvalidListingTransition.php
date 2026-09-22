<?php

namespace App\Exceptions;

use App\Enums\ListingStatus;
use App\Models\Listing;
use DomainException;

/**
 * Thrown by the listing Actions when the current status does not allow the requested change.
 * Components translate it into a message for the user.
 */
class InvalidListingTransition extends DomainException
{
    public function __construct(
        public readonly ListingStatus $from,
        public readonly ListingStatus $to,
    ) {
        parent::__construct(sprintf('A listing cannot go from "%s" to "%s".', $from->value, $to->value));
    }

    public static function for(Listing $listing, ListingStatus $to): self
    {
        return new self($listing->status, $to);
    }

    public function userMessage(): string
    {
        return __('This action is not available for a listing that is “:status”.', ['status' => mb_strtolower($this->from->label())]);
    }
}
