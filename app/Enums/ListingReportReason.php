<?php

namespace App\Enums;

/**
 * Why a visitor reports a listing (docs/08-public-pages-and-flows.md, "Reportar publicación").
 */
enum ListingReportReason: string
{
    case NoLongerAvailable = 'no_longer_available';
    case MisleadingInformation = 'misleading_information';
    case Scam = 'scam';
    case Duplicate = 'duplicate';
    case Inappropriate = 'inappropriate';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NoLongerAvailable => __('The business is no longer available'),
            self::MisleadingInformation => __('The information is false or misleading'),
            self::Scam => __('It looks like a scam'),
            self::Duplicate => __('It is a duplicate of another listing'),
            self::Inappropriate => __('Inappropriate content'),
            self::Other => __('Something else'),
        };
    }
}
