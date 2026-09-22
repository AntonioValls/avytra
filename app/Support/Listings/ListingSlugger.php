<?php

namespace App\Support\Listings;

use App\Models\Listing;
use App\Models\ListingSlugRedirect;
use Illuminate\Support\Str;

/**
 * Unique public slugs for listings (docs/15-seo.md): derived from the title, numeric
 * suffix on collision, never reusing a slug that belongs to a listing (trashed ones
 * included) or that redirects to one, and never colliding with public route segments.
 */
class ListingSlugger
{
    /**
     * Segments used under /empresas/ that a listing slug must not shadow.
     *
     * @var list<string>
     */
    private const array RESERVED = ['categoria', 'provincia', 'nueva', 'editar'];

    private const int MAX_LENGTH = 120;

    public function unique(string $title, ?Listing $ignore = null): string
    {
        $base = Str::limit(Str::slug($title), self::MAX_LENGTH, '');
        $base = trim($base, '-');

        if ($base === '') {
            $base = 'publicacion';
        }

        $candidate = $base;
        $suffix = 1;

        while ($this->isTaken($candidate, $ignore)) {
            $suffix++;
            $candidate = "{$base}-{$suffix}";
        }

        return $candidate;
    }

    public function isTaken(string $slug, ?Listing $ignore = null): bool
    {
        if (in_array($slug, self::RESERVED, true)) {
            return true;
        }

        $listingExists = Listing::withTrashed()
            ->where('slug', $slug)
            ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists();

        return $listingExists || ListingSlugRedirect::query()->where('old_slug', $slug)->exists();
    }
}
