<?php

namespace App\Http\Controllers;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\ListingSlugRedirect;
use App\Support\Listings\PublicListingPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Public listing page (docs/08 "Ficha", docs/15 "Tratamiento de estados").
 *
 * Published and recently sold listings are public. Old sold ones answer 200 with noindex.
 * Archived or deleted ones answer 410. Anything else is 404 for visitors and 200 with a
 * banner for the owner and the superadmin. Old slugs redirect permanently.
 */
class ListingController extends Controller
{
    public function show(Request $request, string $slug): View|RedirectResponse
    {
        $listing = Listing::withTrashed()
            ->with(PublicListingPresenter::RELATIONS)
            ->where('slug', $slug)
            ->first();

        if ($listing === null) {
            return $this->redirectFromOldSlug($slug);
        }

        abort_if($listing->trashed() || $listing->status === ListingStatus::Archived, 410);

        $isPublic = $listing->isPubliclyVisible() || $listing->status === ListingStatus::Sold;
        $viewer = $request->user();

        if (! $isPublic && ($viewer === null || $viewer->cannot('view', $listing))) {
            abort(404);
        }

        $presenter = PublicListingPresenter::for($listing);

        return view('public.listings.show', [
            'listing' => $presenter,
            'meta' => $presenter->pageMeta(),
            'related' => $this->related($listing),
            // The owner sees why the page is not public; visitors never reach this branch.
            'privateStatus' => $isPublic ? null : $listing->status,
            'listingId' => $listing->id,
        ]);
    }

    private function redirectFromOldSlug(string $slug): RedirectResponse
    {
        $redirect = ListingSlugRedirect::query()->where('old_slug', $slug)->with('listing')->first();

        $target = $redirect?->listing;

        abort_if($target === null || $target->slug === null, 404);

        return redirect()->route('listings.show', $target->slug, 301);
    }

    /**
     * A few visible listings of the same sector or province, newest first.
     *
     * @return Collection<int, PublicListingPresenter>
     */
    private function related(Listing $listing): Collection
    {
        $business = $listing->business;
        $provinceId = $business->location?->province_id;

        return Listing::query()
            ->publiclyVisible()
            ->where('status', ListingStatus::Published)
            ->whereKeyNot($listing->getKey())
            ->whereHas('business', function (Builder $query) use ($business, $provinceId): void {
                $query->where('category_id', $business->category_id);

                if ($provinceId !== null) {
                    $query->orWhereHas('location', fn (Builder $location) => $location->where('is_primary', true)->where('province_id', $provinceId));
                }
            })
            ->with(PublicListingPresenter::RELATIONS)
            ->latest('published_at')
            ->orderByDesc('id')
            ->limit((int) config('avytra.public.related_listings'))
            ->get()
            ->map(fn (Listing $related): PublicListingPresenter => PublicListingPresenter::for($related));
    }
}
