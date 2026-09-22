@props([
    'listing',
    'eager' => false,
])

@php
    /** @var \App\Support\Listings\PublicListingPresenter $listing */
    $operations = $listing->operationTypes();
    $primary = $operations[0] ?? null;
    $extra = max(count($operations) - 1, 0);
    $revenue = $listing->disclosedFigure(\App\Enums\FinancialMetric::AnnualRevenue);
@endphp

{{-- Public card (docs/08, "Tarjeta de publicación"). Pure Blade; receives the public presenter, never the model. --}}
<a
    href="{{ $listing->url() }}"
    wire:navigate
    {{ $attributes->class('group flex flex-col overflow-hidden rounded-md border border-zinc-200 bg-white transition hover:border-zinc-300 hover:shadow-md focus:outline-hidden focus-visible:ring-2 focus-visible:ring-transfer focus-visible:ring-offset-2') }}
>
    {{-- Cover placeholder with the brand symbol; real images arrive in Phase 6. --}}
    <div class="relative flex aspect-[16/10] w-full items-center justify-center bg-mist">
        <x-app-logo-icon class="size-14 text-zinc-300" />

        <div class="absolute start-3 top-3 flex flex-wrap gap-1.5">
            @if ($primary)
                <flux:badge size="sm" :color="$primary->badgeColor()">{{ $primary->label() }}@if ($extra > 0) <span class="ms-1 opacity-70">+{{ $extra }}</span>@endif</flux:badge>
            @endif
            @if ($listing->businessType() !== \App\Enums\BusinessType::Physical)
                <flux:badge size="sm" :color="$listing->businessType()->badgeColor()" :icon="$listing->businessType()->icon()">{{ $listing->businessType() === \App\Enums\BusinessType::Hybrid ? __('Online + premises') : __('Online') }}</flux:badge>
            @endif
        </div>

        @if ($listing->isSold())
            <div class="absolute end-3 top-3">
                <flux:badge size="sm" color="blue" icon="check">{{ __('Sold') }}</flux:badge>
            </div>
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-3 p-5">
        <div class="flex flex-col gap-1">
            <h3 class="line-clamp-2 text-lg font-bold leading-6 text-ink group-hover:text-transfer">{{ $listing->title() }}</h3>
            <p class="text-sm text-slate">
                @if ($listing->categoryName()){{ $listing->categoryName() }} · @endif{{ $listing->locationText() }}
            </p>
        </div>

        <x-price :text="$listing->priceText()" :negotiable="$listing->isPriceNegotiable()" />

        @if ($revenue || $listing->employeeRange() || $listing->foundedYear())
            <ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate">
                @if ($revenue)
                    <li class="inline-flex items-center gap-1"><flux:icon.chart-bar variant="micro" /> {{ __('Revenue') }} {{ $revenue }}</li>
                @endif
                @if ($listing->employeeRange())
                    <li class="inline-flex items-center gap-1"><flux:icon.users variant="micro" /> {{ $listing->employeeRange()->label() }}</li>
                @endif
                @if ($listing->foundedYear())
                    <li class="inline-flex items-center gap-1"><flux:icon.calendar variant="micro" /> {{ __('Since :year', ['year' => $listing->foundedYear()]) }}</li>
                @endif
            </ul>
        @endif

        <div class="mt-auto flex items-center justify-between gap-2 pt-2">
            @if ($listing->isSold())
                <span class="text-xs text-slate">{{ __('This business has changed hands.') }}</span>
            @else
                <x-freshness-badge :text="$listing->freshnessText()" :pending="$listing->isPendingRenewal()" />
            @endif
            <span class="inline-flex items-center gap-1 text-xs font-semibold text-transfer">{{ __('See listing') }} <flux:icon.arrow-right variant="micro" /></span>
        </div>
    </div>
</a>
