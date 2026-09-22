<?php

namespace App\Support\Listings;

use App\Enums\BusinessType;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Province;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Counts of visible listings per sector and province, cached briefly, for the home,
 * the footer and the category/province pages (docs/08, docs/19 "Caché").
 *
 * Only plain arrays are cached: the database cache store refuses to unserialise objects.
 */
final class MarketplaceAggregates
{
    private const string CACHE_PREFIX = 'avytra.aggregates.';

    /**
     * Root sectors with the number of visible listings each, sectors without listings included.
     *
     * @return Collection<int, array{id: int, name: string, slug: string, count: int}>
     */
    public function categoriesWithCounts(): Collection
    {
        return collect($this->remember('categories', function (): array {
            return Category::query()
                ->active()
                ->roots()
                ->withCount(['businesses as visible_listings_count' => fn (Builder $query) => $query->whereIn('id', $this->visibleBusinessIds())])
                ->get()
                ->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'count' => (int) $category->getAttribute('visible_listings_count'),
                ])
                ->values()
                ->all();
        }));
    }

    /**
     * Provinces that have at least one visible listing, busiest first.
     *
     * @return Collection<int, array{id: int, name: string, slug: string, count: int}>
     */
    public function provincesWithListings(): Collection
    {
        return collect($this->remember('provinces', function (): array {
            return Province::query()
                ->whereHas('locations', fn (Builder $query) => $query->where('is_primary', true)->whereIn('business_id', $this->visibleBusinessIds()))
                ->withCount(['locations as visible_listings_count' => fn (Builder $query) => $query->where('is_primary', true)->whereIn('business_id', $this->visibleBusinessIds())])
                ->orderByDesc('visible_listings_count')
                ->orderBy('name')
                ->get()
                ->map(fn (Province $province): array => [
                    'id' => $province->id,
                    'name' => $province->name,
                    'slug' => $province->slug,
                    'count' => (int) $province->getAttribute('visible_listings_count'),
                ])
                ->values()
                ->all();
        }));
    }

    /**
     * Every province, for search selects. The catalogue only changes on import.
     *
     * @return Collection<int, array{id: int, name: string, slug: string}>
     */
    public function allProvinces(): Collection
    {
        return collect(Cache::remember(self::CACHE_PREFIX.'all_provinces', now()->addDay(), fn (): array => Province::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Province $province): array => ['id' => $province->id, 'name' => $province->name, 'slug' => $province->slug])
            ->values()
            ->all()));
    }

    public function onlineListingsCount(): int
    {
        return $this->remember('online', fn (): int => Listing::query()
            ->publiclyVisible()
            ->whereHas('business', fn (Builder $query) => $query->where('business_type', BusinessType::Online))
            ->count());
    }

    public function forget(): void
    {
        foreach (['categories', 'provinces', 'online', 'all_provinces'] as $key) {
            Cache::forget(self::CACHE_PREFIX.$key);
        }
    }

    /**
     * Businesses with a visible listing. A business has at most one open listing, so
     * counting businesses equals counting listings.
     *
     * @return Builder<Listing>
     */
    private function visibleBusinessIds(): Builder
    {
        return Listing::query()->publiclyVisible()->select('business_id');
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function remember(string $key, Closure $callback): mixed
    {
        return Cache::remember(self::CACHE_PREFIX.$key, now()->addMinutes((int) config('avytra.public.aggregates_cache_minutes')), $callback);
    }
}
