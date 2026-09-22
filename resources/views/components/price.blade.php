@props([
    'listing',
    'size' => 'md',
])

@php
    /** @var \App\Models\Listing $listing */
    $format = fn (int $amount): string => \Illuminate\Support\Number::currency($amount, $listing->currency, locale: 'es', precision: 0);

    $text = match ($listing->price_disclosure) {
        \App\Enums\PriceDisclosure::Exact => $listing->asking_price === null ? __('On request') : $format($listing->asking_price),
        \App\Enums\PriceDisclosure::Range => $listing->asking_price_min === null || $listing->asking_price_max === null
            ? __('On request')
            : $format($listing->asking_price_min).' – '.$format($listing->asking_price_max),
        \App\Enums\PriceDisclosure::OnRequest => __('On request'),
    };
@endphp

{{-- Asking price as the seller chose to disclose it; never shows a figure the listing does not hold. --}}
<span {{ $attributes->class(['inline-flex flex-wrap items-baseline gap-x-2', 'text-lg font-bold text-ink dark:text-white' => $size === 'md', 'text-2xl font-black text-ink dark:text-white' => $size === 'lg', 'text-sm font-semibold text-ink dark:text-white' => $size === 'sm']) }}>
    <span>{{ $text }}</span>
    @if ($listing->is_price_negotiable)
        <span class="text-xs font-medium text-slate">{{ __('Negotiable') }}</span>
    @endif
</span>
