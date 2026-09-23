@php
    /** @var \App\Support\Listings\PublicListingPresenter $listing */
    $showContact = ! $listing->isSold() && $listing->hasContact();
@endphp

{{-- Listing page (docs/08 "Ficha"). Only the public presenter reaches this view. --}}
<x-layouts::public :meta="$meta">
    @if ($privateStatus !== null)
        <div class="border-b border-amber-200 bg-amber-50">
            <div class="mx-auto max-w-7xl px-6 py-3 lg:px-8">
                <flux:callout icon="eye-slash" variant="warning" inline>
                    <flux:callout.heading>{{ __('This listing is not public (:status).', ['status' => $privateStatus->label()]) }}</flux:callout.heading>
                    <flux:callout.text>{{ __('Only you and AVYTRA can see this page. Visitors receive a "not found" response.') }}</flux:callout.text>
                </flux:callout>
            </div>
        </div>
    @endif

    @if ($listing->isSold())
        <div class="border-b border-blue-200 bg-blue-50">
            <div class="mx-auto max-w-7xl px-6 py-3 lg:px-8">
                <flux:callout icon="check-badge" color="blue" inline>
                    <flux:callout.heading>{{ __('This business has already changed hands.') }}</flux:callout.heading>
                    <flux:callout.text>{{ __('The listing stays visible for a while so you can see what moves on AVYTRA. Similar businesses are listed below.') }}</flux:callout.text>
                </flux:callout>
            </div>
        </div>
    @endif

    <article class="mx-auto flex max-w-7xl flex-col gap-8 px-6 py-8 lg:px-8 lg:py-12">
        {{-- Header --}}
        <header class="flex flex-col gap-4">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('home')" wire:navigate>{{ __('Home') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item :href="route('listings.index')" wire:navigate>{{ __('Businesses') }}</flux:breadcrumbs.item>
                @if ($listing->categorySlug())
                    <flux:breadcrumbs.item :href="route('categories.show', $listing->categorySlug())" wire:navigate>{{ $listing->categoryName() }}</flux:breadcrumbs.item>
                @endif
                <flux:breadcrumbs.item>{{ \Illuminate\Support\Str::limit($listing->title(), 40) }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <div class="flex flex-wrap gap-2">
                @foreach ($listing->operationTypes() as $operation)
                    <flux:badge :color="$operation->badgeColor()">{{ $operation->label() }}@if ($loop->first && $listing->stakePercent()) · {{ $listing->stakePercent() }} %@endif</flux:badge>
                @endforeach
                @if ($listing->businessType() !== \App\Enums\BusinessType::Physical)
                    <flux:badge :color="$listing->businessType()->badgeColor()" :icon="$listing->businessType()->icon()">{{ $listing->businessType()->label() }}</flux:badge>
                @endif
                @if ($listing->isSold())
                    <flux:badge color="blue" icon="check">{{ __('Sold') }}</flux:badge>
                @endif
            </div>

            <h1 class="text-3xl font-black leading-tight tracking-tight text-ink lg:text-4xl">{{ $listing->title() }}</h1>

            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-slate">
                <span class="inline-flex items-center gap-1"><flux:icon.tag variant="micro" /> {{ $listing->categoryName() }}@if ($listing->subcategoryName()) · {{ $listing->subcategoryName() }}@endif</span>
                <span class="inline-flex items-center gap-1"><flux:icon.map-pin variant="micro" /> {{ $listing->locationText() }}</span>
                @if (! $listing->isSold())
                    <x-freshness-badge :text="$listing->freshnessText()" :pending="$listing->isPendingRenewal()" />
                @endif
            </div>
        </header>

        {{-- Gallery (docs/08): cover, thumbnails and an Alpine lightbox. Only WebP conversions reach the HTML; without images, the brand placeholder. --}}
        @php
            $images = $listing->galleryImages();
            $lightbox = array_map(fn (\App\Support\Media\PublicImage $image): array => ['src' => $image->url('detail'), 'alt' => $image->alt], $images);
        @endphp
        <div
            x-data="{ images: @js($lightbox), open: false, current: 0, show(index) { this.current = index; this.open = true }, next() { this.current = (this.current + 1) % this.images.length }, prev() { this.current = (this.current - 1 + this.images.length) % this.images.length } }"
            class="flex flex-col gap-3"
        >
            @if ($images === [])
                <div class="flex aspect-[16/9] w-full items-center justify-center overflow-hidden rounded-lg bg-mist lg:aspect-[21/9]">
                    <div class="flex flex-col items-center gap-2 text-zinc-400">
                        <x-app-logo-icon class="size-16" />
                        <span class="text-xs">{{ __('No photos yet') }}</span>
                    </div>
                </div>
            @else
                @php $first = $images[0]; @endphp
                <button type="button" x-on:click="show(0)" class="group relative aspect-[16/9] w-full overflow-hidden rounded-lg bg-mist lg:aspect-[21/9] focus:outline-hidden focus-visible:ring-2 focus-visible:ring-transfer focus-visible:ring-offset-2" aria-label="{{ __('Open the photos') }}">
                    <img
                        src="{{ $first->url('detail') }}"
                        srcset="{{ $first->srcset('card', 'detail') }}"
                        sizes="(min-width: 1280px) 1216px, 100vw"
                        width="{{ $first->width('detail') }}"
                        height="{{ $first->height('detail') }}"
                        alt="{{ $first->alt }}"
                        fetchpriority="high"
                        class="size-full object-cover transition duration-300 group-hover:scale-[1.01]"
                    />
                    @if (count($images) > 1)
                        <span class="absolute bottom-3 end-3 inline-flex items-center gap-1 rounded-full bg-ink/80 px-3 py-1 text-xs font-semibold text-white"><flux:icon.photo variant="micro" /> {{ __(':count photos', ['count' => count($images)]) }}</span>
                    @endif
                </button>

                @if (count($images) > 1)
                    <ul class="grid grid-cols-4 gap-2 sm:grid-cols-6 lg:grid-cols-8">
                        @foreach ($images as $index => $image)
                            <li>
                                <button type="button" x-on:click="show({{ $index }})" class="block aspect-[16/10] w-full overflow-hidden rounded-sm bg-mist focus:outline-hidden focus-visible:ring-2 focus-visible:ring-transfer focus-visible:ring-offset-2">
                                    <img src="{{ $image->url('thumb') }}" width="{{ $image->width('thumb') }}" height="{{ $image->height('thumb') }}" alt="{{ $image->alt }}" loading="lazy" class="size-full object-cover" />
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endif

            <template x-if="open">
                <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink/95 p-4" x-on:keydown.escape.window="open = false" x-on:keydown.arrow-right.window="next()" x-on:keydown.arrow-left.window="prev()" x-on:click.self="open = false" role="dialog" aria-modal="true">
                    <button type="button" class="absolute end-4 top-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" x-on:click="open = false" aria-label="{{ __('Close') }}"><flux:icon.x-mark /></button>
                    <template x-if="images.length > 1">
                        <div>
                            <button type="button" class="absolute start-4 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" x-on:click="prev()" aria-label="{{ __('Previous') }}"><flux:icon.chevron-left /></button>
                            <button type="button" class="absolute end-4 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" x-on:click="next()" aria-label="{{ __('Next') }}"><flux:icon.chevron-right /></button>
                        </div>
                    </template>
                    <figure class="flex max-h-full max-w-6xl flex-col items-center gap-3">
                        <img :src="images[current]?.src" :alt="images[current]?.alt" class="max-h-[85vh] max-w-full rounded-md object-contain" />
                        <figcaption class="text-sm text-white/80" x-text="(images[current]?.alt || '') + ' · ' + (current + 1) + ' / ' + images.length"></figcaption>
                    </figure>
                </div>
            </template>
        </div>

        <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_360px]">
            <div class="flex flex-col gap-10">
                @include('public.listings.partials.content', ['listing' => $listing])
            </div>

            <aside class="flex flex-col gap-6 lg:sticky lg:top-6 lg:self-start" id="contacto">
                @if ($showContact)
                    <livewire:public.contact-box :listing-id="$listingId" />
                @elseif ($listing->isSold())
                    <flux:card class="flex flex-col gap-2">
                        <flux:heading size="lg">{{ __('Sold') }}</flux:heading>
                        <flux:text>{{ __('This listing no longer accepts contacts. Take a look at similar businesses below.') }}</flux:text>
                    </flux:card>
                @endif

                <flux:card class="flex flex-col gap-3">
                    <div class="flex items-center gap-3">
                        @if ($logo = $listing->logoImage())
                            <img src="{{ $logo->url('logo') }}" alt="{{ $logo->alt }}" width="48" height="48" loading="lazy" class="size-12 shrink-0 rounded-sm object-contain" />
                        @endif
                        <flux:heading size="lg">{{ __('About the business') }}</flux:heading>
                    </div>
                    <dl class="flex flex-col gap-2 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-slate">{{ __('Business') }}</dt><dd class="text-end font-semibold text-ink">{{ $listing->businessName() }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-slate">{{ __('Sector') }}</dt><dd class="text-end font-semibold text-ink">{{ $listing->categoryName() }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-slate">{{ __('Type') }}</dt><dd class="text-end font-semibold text-ink">{{ $listing->businessType()->label() }}</dd></div>
                        @if ($listing->legalForm())
                            <div class="flex justify-between gap-4"><dt class="text-slate">{{ __('Legal form') }}</dt><dd class="text-end font-semibold text-ink">{{ $listing->legalForm()->label() }}</dd></div>
                        @endif
                        @if ($listing->websiteUrl())
                            <div class="flex justify-between gap-4"><dt class="text-slate">{{ __('Website') }}</dt><dd class="text-end"><a href="{{ $listing->websiteUrl() }}" target="_blank" rel="nofollow noopener ugc" class="font-semibold text-transfer hover:underline">{{ __('Visit') }}</a></dd></div>
                        @endif
                        @if ($listing->publishedAt())
                            <div class="flex justify-between gap-4"><dt class="text-slate">{{ __('Published') }}</dt><dd class="text-end font-semibold text-ink">{{ $listing->publishedAt()->translatedFormat('j \d\e F \d\e Y') }}</dd></div>
                        @endif
                        @if ($listing->freshnessText())
                            <div class="flex justify-between gap-4"><dt class="text-slate">{{ __('Availability') }}</dt><dd class="text-end font-semibold text-ink">{{ $listing->freshnessText() }}</dd></div>
                        @endif
                    </dl>
                </flux:card>

                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <div x-data="{ copied: false }">
                        <flux:button
                            size="sm"
                            variant="ghost"
                            icon="link"
                            x-on:click="navigator.clipboard.writeText(@js($listing->url() ?? url()->current())).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                        >
                            <span x-show="! copied">{{ __('Copy link') }}</span>
                            <span x-show="copied" x-cloak>{{ __('Link copied') }}</span>
                        </flux:button>
                    </div>
                    @if ($privateStatus === null)
                        <livewire:public.report-listing :listing-id="$listingId" />
                    @endif
                </div>
            </aside>
        </div>

        @if ($related->isNotEmpty())
            <section class="flex flex-col gap-6 border-t border-zinc-200 pt-10">
                <flux:heading size="xl" level="2">{{ __('Similar businesses') }}</flux:heading>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($related as $card)
                        <x-listing-card :listing="$card" />
                    @endforeach
                </div>
            </section>
        @endif
    </article>

    @if ($showContact)
        {{-- Mobile: fixed bar that jumps to the contact block (docs/14 "Responsive y móvil"). --}}
        <div class="fixed inset-x-0 bottom-0 z-30 border-t border-zinc-200 bg-white/95 p-3 backdrop-blur lg:hidden">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-3">
                <x-price :text="$listing->priceText()" :negotiable="$listing->isPriceNegotiable()" size="sm" />
                <flux:button variant="primary" href="#contacto" icon="chat-bubble-left-right">{{ __('Contact') }}</flux:button>
            </div>
        </div>
        <div class="h-20 lg:hidden" aria-hidden="true"></div>
    @endif
</x-layouts::public>
