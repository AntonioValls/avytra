<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Support\Listings\MarketplaceAggregates;
use App\Support\Listings\PublicListingPresenter;
use App\Support\Seo\PageMeta;
use Illuminate\View\View;

/**
 * Home (docs/08): hero with search, quick links, latest listings, how it works, trust block.
 */
class HomeController extends Controller
{
    public function __invoke(MarketplaceAggregates $aggregates): View
    {
        $latest = Listing::query()
            ->publiclyVisible()
            ->with(PublicListingPresenter::RELATIONS)
            ->latest('published_at')
            ->orderByDesc('id')
            ->limit((int) config('avytra.public.home_latest_listings'))
            ->get()
            ->map(fn (Listing $listing): PublicListingPresenter => PublicListingPresenter::for($listing));

        $categories = $aggregates->categoriesWithCounts();

        $meta = new PageMeta(
            title: __('Businesses that change hands.'),
            description: __('Businesses for sale or transfer, with clear data and confirmed availability.'),
            canonical: route('home'),
            jsonLd: [[
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => config('app.name'),
                'url' => route('home'),
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => route('listings.index').'?q={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ],
            ]],
        );

        return view('public.home', [
            'meta' => $meta,
            'latest' => $latest,
            'topCategories' => $categories->sortByDesc('count')->take(6)->values(),
            'topProvinces' => $aggregates->provincesWithListings()->take(6),
            'provinces' => $aggregates->allProvinces(),
            'onlineCount' => $aggregates->onlineListingsCount(),
        ]);
    }
}
