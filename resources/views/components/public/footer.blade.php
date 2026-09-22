@php
    $supportEmail = config('avytra.support.email');
    $supportPhone = config('avytra.support.phone');
    $aggregates = app(\App\Support\Listings\MarketplaceAggregates::class);
    $footerCategories = $aggregates->categoriesWithCounts()->where('count', '>', 0)->sortByDesc('count')->take(8);
    $footerProvinces = $aggregates->provincesWithListings()->take(8);
@endphp

<footer class="mt-24 bg-ink text-white">
    <div class="mx-auto grid max-w-7xl gap-10 px-6 py-14 lg:grid-cols-[1.4fr_1fr_1fr_1fr] lg:px-8">
        <div class="flex flex-col gap-4">
            <x-app-logo class="text-white" wire:navigate />
            <p class="max-w-sm text-sm leading-6 text-zinc-300">{{ __('Businesses that change hands.') }}</p>
            <p class="max-w-sm text-sm leading-6 text-zinc-400">{{ __('A free showcase to discover, publish, sell and transfer businesses. AVYTRA connects; it does not intermediate.') }}</p>
        </div>

        <nav class="flex flex-col gap-3 text-sm text-zinc-300" aria-label="{{ __('Sectors') }}">
            <span class="text-xs font-bold uppercase tracking-wider text-zinc-400">{{ __('Sectors') }}</span>
            @forelse ($footerCategories as $category)
                <a href="{{ route('categories.show', $category['slug']) }}" wire:navigate class="hover:text-white">{{ $category['name'] }}</a>
            @empty
                <a href="{{ route('listings.index') }}" wire:navigate class="hover:text-white">{{ __('Explore businesses') }}</a>
            @endforelse
            <a href="{{ route('listings.online') }}" wire:navigate class="hover:text-white">{{ __('Online businesses') }}</a>
        </nav>

        <nav class="flex flex-col gap-3 text-sm text-zinc-300" aria-label="{{ __('Provinces') }}">
            <span class="text-xs font-bold uppercase tracking-wider text-zinc-400">{{ __('Provinces') }}</span>
            @forelse ($footerProvinces as $province)
                <a href="{{ route('provinces.show', $province['slug']) }}" wire:navigate class="hover:text-white">{{ $province['name'] }}</a>
            @empty
                <span class="text-zinc-500">{{ __('Soon, businesses in every province.') }}</span>
            @endforelse
        </nav>

        <nav class="flex flex-col gap-3 text-sm text-zinc-300" aria-label="{{ config('app.name') }}">
            <span class="text-xs font-bold uppercase tracking-wider text-zinc-400">{{ config('app.name') }}</span>
            <a href="{{ route('publish.landing') }}" wire:navigate class="hover:text-white">{{ __('Sell your business') }}</a>
            <a href="{{ route('how-it-works') }}" wire:navigate class="hover:text-white">{{ __('How it works') }}</a>
            <a href="{{ route('legal.notice') }}" wire:navigate class="hover:text-white">{{ __('Legal notice') }}</a>
            <a href="{{ route('legal.privacy') }}" wire:navigate class="hover:text-white">{{ __('Privacy') }}</a>
            <a href="{{ route('legal.cookies') }}" wire:navigate class="hover:text-white">{{ __('Cookies') }}</a>
            @if ($supportEmail)
                <a href="mailto:{{ $supportEmail }}" class="hover:text-white">{{ $supportEmail }}</a>
            @endif
            @if ($supportPhone)
                <a href="tel:{{ $supportPhone }}" class="hover:text-white">{{ $supportPhone }}</a>
            @endif
        </nav>
    </div>

    <div class="border-t border-white/10">
        <div class="mx-auto flex max-w-7xl flex-col gap-2 px-6 py-6 text-xs text-zinc-500 sm:flex-row sm:items-center sm:justify-between lg:px-8">
            <span>© {{ now()->year }} {{ config('app.name') }}. {{ __('All rights reserved.') }}</span>
            <span>{{ __('Buy. Sell. Continue.') }}</span>
        </div>
    </div>
</footer>
