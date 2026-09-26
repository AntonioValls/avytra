<?php

namespace App\Support\Seo;

use App\Models\Listing;
use App\Support\Listings\MarketplaceAggregates;
use Illuminate\Support\Facades\Cache;

/**
 * The XML sitemap (docs/15): home, explore, static pages, sectors and provinces with visible
 * listings and every visible listing with its lastmod. Cached for an hour and forgotten
 * whenever a listing changes status or URL (RecordsListingEvents, ChangeListingSlug).
 * One file is enough until 50,000 URLs; an index comes later if ever needed.
 */
final class Sitemap
{
    public const CACHE_KEY = 'avytra.sitemap';

    public function __construct(private MarketplaceAggregates $aggregates) {}

    public function xml(): string
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes((int) config('avytra.seo.sitemap_cache_minutes')), fn (): string => $this->build());
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return list<array{loc: string, lastmod?: string, changefreq?: string, priority?: string}>
     */
    public function urls(): array
    {
        $urls = [
            ['loc' => route('home'), 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => route('listings.index'), 'changefreq' => 'hourly', 'priority' => '0.9'],
            ['loc' => route('publish.landing'), 'changefreq' => 'monthly', 'priority' => '0.6'],
            ['loc' => route('how-it-works'), 'changefreq' => 'monthly', 'priority' => '0.5'],
            ['loc' => route('legal.notice'), 'changefreq' => 'yearly', 'priority' => '0.2'],
            ['loc' => route('legal.privacy'), 'changefreq' => 'yearly', 'priority' => '0.2'],
            ['loc' => route('legal.cookies'), 'changefreq' => 'yearly', 'priority' => '0.2'],
        ];

        if ($this->aggregates->onlineListingsCount() > 0) {
            $urls[] = ['loc' => route('listings.online'), 'changefreq' => 'daily', 'priority' => '0.8'];
        }

        foreach ($this->aggregates->categoriesWithCounts() as $category) {
            if ($category['count'] > 0) {
                $urls[] = ['loc' => route('categories.show', $category['slug']), 'changefreq' => 'daily', 'priority' => '0.8'];
            }
        }

        foreach ($this->aggregates->provincesWithListings() as $province) {
            $urls[] = ['loc' => route('provinces.show', $province['slug']), 'changefreq' => 'daily', 'priority' => '0.7'];
        }

        $listings = Listing::query()
            ->publiclyVisible()
            ->whereNotNull('slug')
            ->select(['id', 'slug', 'status', 'updated_at', 'sold_at'])
            ->lazyById(500);

        foreach ($listings as $listing) {
            $url = ['loc' => route('listings.show', $listing->slug), 'changefreq' => 'weekly', 'priority' => '0.8'];
            $lastmod = $listing->sold_at ?? $listing->updated_at;

            if ($lastmod !== null) {
                $url['lastmod'] = $lastmod->toAtomString();
            }

            $urls[] = $url;
        }

        return $urls;
    }

    private function build(): string
    {
        $lines = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];

        foreach ($this->urls() as $url) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>'.htmlspecialchars($url['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc>';

            if (isset($url['lastmod'])) {
                $lines[] = '    <lastmod>'.$url['lastmod'].'</lastmod>';
            }

            if (isset($url['changefreq'])) {
                $lines[] = '    <changefreq>'.$url['changefreq'].'</changefreq>';
            }

            if (isset($url['priority'])) {
                $lines[] = '    <priority>'.$url['priority'].'</priority>';
            }

            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines)."\n";
    }
}
