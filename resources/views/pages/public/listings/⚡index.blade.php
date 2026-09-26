<?php

use App\Enums\BusinessType;
use App\Enums\OperationType;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Province;
use App\Support\Listings\MarketplaceAggregates;
use App\Support\Listings\PublicListingPresenter;
use App\Support\Seo\PageMeta;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Explore (docs/08): filters in the URL so results are shareable, always paginated.
 * The same component serves /empresas, the category and province pages and /negocios-online,
 * each with one filter fixed by the route and its own texts and metadata.
 */
new class extends Component {
    use WithPagination;

    /** Values accepted by the "tipo" parameter, in Spanish as the URL is. */
    private const array TYPES = ['fisico' => BusinessType::Physical, 'online' => BusinessType::Online, 'hibrido' => BusinessType::Hybrid];

    private const array SORTS = ['recientes', 'precio_asc', 'precio_desc', 'confirmadas'];

    #[Locked]
    public ?int $fixedCategoryId = null;

    #[Locked]
    public ?int $fixedProvinceId = null;

    #[Locked]
    public bool $onlineOnly = false;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sector', except: '')]
    public string $sector = '';

    #[Url(as: 'tipo', except: '')]
    public string $type = '';

    #[Url(as: 'operacion', except: '')]
    public string $operation = '';

    #[Url(as: 'provincia', except: '')]
    public string $province = '';

    #[Url(as: 'precio_min', except: '')]
    public string $priceMin = '';

    #[Url(as: 'precio_max', except: '')]
    public string $priceMax = '';

    #[Url(as: 'orden', except: 'recientes')]
    public string $sort = 'recientes';

    #[Url(as: 'mapa', except: false)]
    public bool $showMap = false;

    public function mount(?Category $category = null, ?Province $province = null, bool $online = false): void
    {
        if ($category !== null) {
            abort_unless($category->is_active && $category->isRoot(), 404);
            $this->fixedCategoryId = $category->id;
            $this->sector = '';
        }

        if ($province !== null) {
            $this->fixedProvinceId = $province->id;
            $this->province = '';
        }

        if ($online) {
            $this->onlineOnly = true;
            $this->type = '';
        }
    }

    public function rendering(View $view): void
    {
        $view->title($this->pageTitle());
        $view->layout('layouts::public', ['meta' => $this->pageMeta()]);
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'sector', 'type', 'operation', 'province', 'priceMin', 'priceMax', 'sort');
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Listing>
     */
    #[Computed]
    public function results(): LengthAwarePaginator
    {
        $search = trim($this->search);
        $categoryId = $this->fixedCategoryId ?? $this->categoryIdFromSlug($this->sector);
        $provinceId = $this->fixedProvinceId ?? $this->provinceIdFromSlug($this->province);
        $type = $this->onlineOnly ? BusinessType::Online : (self::TYPES[$this->type] ?? null);
        $operation = OperationType::tryFrom($this->operation);
        $priceMin = $this->integerOrNull($this->priceMin);
        $priceMax = $this->integerOrNull($this->priceMax);

        return Listing::query()
            ->publiclyVisible()
            ->with(PublicListingPresenter::RELATIONS)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhereHas('business', fn (Builder $business) => $business->where('name', 'like', "%{$search}%")->orWhere('tagline', 'like', "%{$search}%"));
                });
            })
            ->when($categoryId !== null, fn (Builder $query) => $query->whereHas('business', fn (Builder $business) => $business->where('category_id', $categoryId)))
            ->when($type !== null, fn (Builder $query) => $query->whereHas('business', fn (Builder $business) => $business->where('business_type', $type)))
            ->when($provinceId !== null, fn (Builder $query) => $query->whereHas('business.location', fn (Builder $location) => $location->where('is_primary', true)->where('province_id', $provinceId)))
            ->when($operation !== null, fn (Builder $query) => $query->whereHas('operationTypes', fn (Builder $types) => $types->where('operation_type', $operation)))
            // Price filters compare against the exact price or the range bound that can satisfy them; "on request" listings have no price to compare.
            ->when($priceMin !== null, fn (Builder $query) => $query->whereRaw('COALESCE(asking_price, asking_price_max) >= ?', [$priceMin]))
            ->when($priceMax !== null, fn (Builder $query) => $query->whereRaw('COALESCE(asking_price, asking_price_min) <= ?', [$priceMax]))
            ->tap(fn (Builder $query) => $this->applySort($query))
            ->paginate((int) config('avytra.pagination.public_cards'));
    }

    /**
     * @return SupportCollection<int, PublicListingPresenter>
     */
    #[Computed]
    public function cards(): SupportCollection
    {
        return $this->results->getCollection()->map(fn (Listing $listing): PublicListingPresenter => PublicListingPresenter::for($listing));
    }

    /**
     * Public points of the current page only (docs/10): never all listings, never private coordinates.
     *
     * @return list<array{lat: float, lng: float, radius: int|null, title: string, url: string|null, text: string}>
     */
    #[Computed]
    public function mapPoints(): array
    {
        return $this->cards
            ->map(fn (PublicListingPresenter $card): ?array => $card->mapPoint())
            ->filter()
            ->values()
            ->all();
    }

    public function toggleMap(): void
    {
        $this->showMap = ! $this->showMap;
    }

    /**
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categories(): Collection
    {
        return Category::query()->active()->roots()->get();
    }

    /**
     * @return SupportCollection<int, array{id: int, name: string, slug: string}>
     */
    #[Computed]
    public function provinces(): SupportCollection
    {
        return app(MarketplaceAggregates::class)->allProvinces();
    }

    #[Computed]
    public function fixedCategory(): ?Category
    {
        return $this->fixedCategoryId === null ? null : Category::query()->find($this->fixedCategoryId);
    }

    #[Computed]
    public function fixedProvince(): ?Province
    {
        return $this->fixedProvinceId === null ? null : Province::query()->find($this->fixedProvinceId);
    }

    #[Computed]
    public function activeFilterCount(): int
    {
        return count(array_filter([$this->search, $this->sector, $this->type, $this->operation, $this->province, $this->priceMin, $this->priceMax], fn (string $value): bool => trim($value) !== ''));
    }

    public function pageTitle(): string
    {
        if ($this->fixedCategory !== null) {
            return __(':sector for sale and transfer', ['sector' => $this->fixedCategory->name]);
        }

        if ($this->fixedProvince !== null) {
            return __('Businesses for sale in :province', ['province' => $this->fixedProvince->name]);
        }

        if ($this->onlineOnly) {
            return __('Online businesses for sale');
        }

        return __('Businesses for sale or transfer');
    }

    public function pageIntro(): string
    {
        if ($this->fixedCategory !== null) {
            return $this->fixedCategory->description ?? __('Businesses in the :sector sector offered for sale or transfer, with confirmed availability.', ['sector' => mb_strtolower($this->fixedCategory->name)]);
        }

        if ($this->fixedProvince !== null) {
            $total = $this->results->total();
            $sectors = $total > 0 ? $this->sectorsInResults() : [];

            if ($total > 0 && $sectors !== []) {
                return trans_choice('{1} One business for sale or transfer in the province of :province, with confirmed availability. Sectors: :sectors.|[2,*] :count businesses for sale or transfer in the province of :province, with confirmed availability. Sectors: :sectors.', $total, ['province' => $this->fixedProvince->name, 'sectors' => mb_strtolower(implode(', ', $sectors))]);
            }

            return __('Businesses and companies in the province of :province offered for sale or transfer, with confirmed availability.', ['province' => $this->fixedProvince->name]);
        }

        if ($this->onlineOnly) {
            return __('Ecommerce, SaaS, marketplaces and other businesses that operate only online.');
        }

        return __('Every business published on AVYTRA. Filter by sector, type, operation, province and price.');
    }

    /**
     * Base URL of this page without query string: canonical for filtered views.
     */
    public function baseUrl(): string
    {
        if ($this->fixedCategory !== null) {
            return route('categories.show', $this->fixedCategory);
        }

        if ($this->fixedProvince !== null) {
            return route('provinces.show', $this->fixedProvince);
        }

        return $this->onlineOnly ? route('listings.online') : route('listings.index');
    }

    private function pageMeta(): PageMeta
    {
        $isLandingPage = $this->fixedCategory !== null || $this->fixedProvince !== null || $this->onlineOnly;

        // A sector or province chosen on /empresas has a URL of its own: point search engines there.
        $canonical = match (true) {
            $isLandingPage => $this->baseUrl(),
            $this->sector !== '' && $this->categoryIdFromSlug($this->sector) !== null && $this->province === '' => route('categories.show', $this->sector),
            $this->province !== '' && $this->provinceIdFromSlug($this->province) !== null && $this->sector === '' => route('provinces.show', $this->province),
            default => $this->baseUrl(),
        };

        $isEmpty = $this->results->total() === 0;

        return new PageMeta(
            title: $this->pageTitle(),
            description: $this->pageIntro(),
            canonical: $canonical,
            robots: $isLandingPage && $isEmpty ? 'noindex,follow' : null,
            // ItemList and BreadcrumbList only on the landing pages with content (docs/15).
            jsonLd: $isLandingPage && ! $isEmpty ? [$this->itemListJsonLd(), $this->breadcrumbJsonLd()] : [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function itemListJsonLd(): array
    {
        $offset = ($this->results->currentPage() - 1) * $this->results->perPage();

        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $this->pageTitle(),
            'numberOfItems' => $this->results->total(),
            'itemListElement' => $this->cards
                ->filter(fn (PublicListingPresenter $card): bool => $card->url() !== null)
                ->values()
                ->map(fn (PublicListingPresenter $card, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $offset + $index + 1,
                    'name' => $card->title(),
                    'url' => $card->url(),
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function breadcrumbJsonLd(): array
    {
        $items = [
            ['name' => __('Home'), 'item' => route('home')],
            ['name' => __('Businesses'), 'item' => route('listings.index')],
            ['name' => $this->fixedCategory?->name ?? $this->fixedProvince?->name ?? __('Online businesses'), 'item' => $this->baseUrl()],
        ];

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $item, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['item'],
            ], $items, array_keys($items)),
        ];
    }

    /**
     * Sectors present among the results of a province page, for its meta description.
     *
     * @return list<string>
     */
    private function sectorsInResults(): array
    {
        return Category::query()
            ->active()
            ->roots()
            ->whereHas('businesses', fn (Builder $business) => $business->whereIn('id', Listing::query()
                ->publiclyVisible()
                ->whereHas('business.location', fn (Builder $location) => $location->where('is_primary', true)->where('province_id', $this->fixedProvinceId))
                ->select('business_id')))
            ->limit(5)
            ->pluck('name')
            ->all();
    }

    /**
     * @param  Builder<Listing>  $query
     */
    private function applySort(Builder $query): void
    {
        match (in_array($this->sort, self::SORTS, true) ? $this->sort : 'recientes') {
            'precio_asc' => $query->orderByRaw('COALESCE(asking_price, asking_price_min) IS NULL')->orderByRaw('COALESCE(asking_price, asking_price_min) ASC'),
            'precio_desc' => $query->orderByRaw('COALESCE(asking_price, asking_price_max) IS NULL')->orderByRaw('COALESCE(asking_price, asking_price_max) DESC'),
            'confirmadas' => $query->orderByDesc('last_confirmed_at'),
            default => $query->orderByDesc('published_at'),
        };

        $query->orderByDesc('id');
    }

    private function categoryIdFromSlug(string $slug): ?int
    {
        if ($slug === '') {
            return null;
        }

        return $this->categories->firstWhere('slug', $slug)?->id;
    }

    private function provinceIdFromSlug(string $slug): ?int
    {
        if ($slug === '') {
            return null;
        }

        return $this->provinces->firstWhere('slug', $slug)['id'] ?? null;
    }

    private function integerOrNull(string $value): ?int
    {
        $value = trim($value);

        if ($value === '' || ! ctype_digit($value)) {
            return null;
        }

        return min((int) $value, (int) config('avytra.public.price_filter_max'));
    }
}; ?>

<div>
    <section class="border-b border-zinc-200 bg-mist">
        <div class="mx-auto flex max-w-7xl flex-col gap-4 px-6 py-10 lg:px-8 lg:py-14">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('home')" wire:navigate>{{ __('Home') }}</flux:breadcrumbs.item>
                @if ($this->fixedCategory !== null || $this->fixedProvince !== null || $onlineOnly)
                    <flux:breadcrumbs.item :href="route('listings.index')" wire:navigate>{{ __('Businesses') }}</flux:breadcrumbs.item>
                    <flux:breadcrumbs.item>{{ $this->fixedCategory?->name ?? $this->fixedProvince?->name ?? __('Online businesses') }}</flux:breadcrumbs.item>
                @else
                    <flux:breadcrumbs.item>{{ __('Businesses') }}</flux:breadcrumbs.item>
                @endif
            </flux:breadcrumbs>

            <h1 class="text-3xl font-black tracking-tight text-ink lg:text-4xl">{{ $this->pageTitle() }}</h1>
            <p class="max-w-3xl text-base leading-6 text-slate">{{ $this->pageIntro() }}</p>
        </div>
    </section>

    <section class="mx-auto flex max-w-7xl flex-col gap-8 px-6 py-10 lg:flex-row lg:px-8">
        {{-- Filters: fixed sidebar on desktop, flyout on mobile. --}}
        <aside class="hidden w-72 shrink-0 lg:block">
            <div class="sticky top-6 flex flex-col gap-5 rounded-md border border-zinc-200 bg-white p-5">
                @include('public.listings.partials.filters')
            </div>
        </aside>

        <div class="flex flex-1 flex-col gap-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate" aria-live="polite">
                    {{ trans_choice('{0} No businesses|{1} :count business|[2,*] :count businesses', $this->results->total(), ['count' => number_format($this->results->total(), 0, ',', '.')]) }}
                </p>

                <div class="flex items-center gap-2">
                    <flux:modal.trigger name="filters">
                        <flux:button icon="adjustments-horizontal" class="lg:hidden">
                            {{ __('Filters') }}@if ($this->activeFilterCount > 0) ({{ $this->activeFilterCount }})@endif
                        </flux:button>
                    </flux:modal.trigger>

                    @if ($this->cards->isNotEmpty())
                        <flux:button wire:click="toggleMap" :icon="$showMap ? 'list-bullet' : 'map'" :aria-pressed="$showMap ? 'true' : 'false'">
                            {{ $showMap ? __('Hide map') : __('Show map') }}
                        </flux:button>
                    @endif

                    <flux:select variant="listbox" wire:model.live="sort" class="w-52" :aria-label="__('Sort by')">
                        <flux:select.option value="recientes">{{ __('Most recent') }}</flux:select.option>
                        <flux:select.option value="confirmadas">{{ __('Recently confirmed') }}</flux:select.option>
                        <flux:select.option value="precio_asc">{{ __('Price: low to high') }}</flux:select.option>
                        <flux:select.option value="precio_desc">{{ __('Price: high to low') }}</flux:select.option>
                    </flux:select>
                </div>
            </div>

            @if ($this->cards->isEmpty())
                <x-empty-state icon="magnifying-glass" :heading="__('No businesses match your search.')" :text="__('Try fewer filters or another province. New listings are published every week.')">
                    @if ($this->activeFilterCount > 0)
                        <flux:button wire:click="clearFilters" icon="x-mark">{{ __('Clear filters') }}</flux:button>
                    @endif
                    <flux:button variant="primary" :href="route('publish.landing')" wire:navigate icon-trailing="arrow-right">{{ __('Have a business? Publish it for free') }}</flux:button>
                </x-empty-state>
            @else
                @if ($showMap)
                    <x-map.explore :points="$this->mapPoints" wire:key="explore-map" />
                @endif

                <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3" wire:loading.class="opacity-60">
                    @foreach ($this->cards as $card)
                        <x-listing-card :listing="$card" wire:key="card-{{ $card->id() }}" />
                    @endforeach
                </div>

                <flux:pagination :paginator="$this->results" scroll-to />
            @endif
        </div>
    </section>

    <flux:modal name="filters" flyout position="bottom" class="lg:hidden">
        <div class="flex flex-col gap-5">
            <flux:heading size="lg">{{ __('Filters') }}</flux:heading>
            @include('public.listings.partials.filters')
            <flux:modal.close>
                <flux:button variant="primary" class="w-full">{{ __('See results') }}</flux:button>
            </flux:modal.close>
        </div>
    </flux:modal>
</div>
