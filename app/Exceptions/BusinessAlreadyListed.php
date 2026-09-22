<?php

namespace App\Exceptions;

use App\Models\Business;
use DomainException;

/**
 * A business may have at most one listing that is not sold nor archived (domain invariant 2).
 */
class BusinessAlreadyListed extends DomainException
{
    public function __construct(public readonly Business $business)
    {
        parent::__construct(sprintf('Business %d already has an open listing.', $business->getKey()));
    }

    public function userMessage(): string
    {
        return __('This business already has a listing in progress. Finish, archive or sell it before creating another one.');
    }
}
