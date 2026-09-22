<?php

namespace App\Exceptions;

use App\Support\Listings\PublishabilityReport;
use DomainException;

/**
 * Thrown by PublishListing when the "ready to publish" validation finds missing data.
 */
class ListingNotPublishable extends DomainException
{
    public function __construct(public readonly PublishabilityReport $report)
    {
        parent::__construct('The listing is missing data required to publish.');
    }

    public function userMessage(): string
    {
        return __('Some required information is missing. Review the steps marked below.');
    }
}
