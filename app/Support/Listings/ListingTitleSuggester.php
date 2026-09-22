<?php

namespace App\Support\Listings;

use App\Models\Listing;
use Illuminate\Support\Str;

/**
 * "{Operation} of {sector} in {municipality|province|online}", the default title the
 * owner may keep or rewrite (docs/09-dashboard-and-admin.md).
 */
class ListingTitleSuggester
{
    public function suggest(Listing $listing): ?string
    {
        $listing->loadMissing(['business.category', 'business.location.municipality', 'business.location.province']);

        $operation = $listing->primary_operation_type;
        $business = $listing->business;

        if ($operation === null || $business->category === null) {
            return null;
        }

        $sector = Str::lower($business->category->name);
        $place = $this->place($listing);

        $title = $place === null
            ? __(':operation :sector online', ['operation' => $operation->titlePrefix(), 'sector' => $sector])
            : __(':operation :sector in :place', ['operation' => $operation->titlePrefix(), 'sector' => $sector, 'place' => $place]);

        return Str::limit(Str::ucfirst(trim($title)), (int) config('avytra.limits.title_max_length'), '');
    }

    private function place(Listing $listing): ?string
    {
        $location = $listing->business->location;

        if ($location === null) {
            return null;
        }

        return $location->municipality === null ? $location->province->name : $location->municipality->name;
    }
}
