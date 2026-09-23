<?php

namespace App\Exceptions;

use App\Models\Business;
use DomainException;

/**
 * The gallery of a business holds at most config('avytra.media.gallery_max') images (docs/17).
 */
class GalleryFull extends DomainException
{
    public function __construct(public readonly Business $business, public readonly int $max)
    {
        parent::__construct(sprintf('Business %d already has %d gallery images.', $business->getKey(), $max));
    }

    public function userMessage(): string
    {
        return __('The gallery is full: up to :max images per business.', ['max' => $this->max]);
    }
}
