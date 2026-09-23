@props([
    'points' => [],
])

@php
    /** @var list<array{lat: float, lng: float, radius: int|null, title: string, url: string|null, text: string}> $points */
    $centre = config('avytra.map.default_centre');
@endphp

{{--
    Optional map on explore (docs/08 "Vista de mapa"): only the listings of the current page that
    have a public location, with circles for approximate areas. The points travel in data-points
    and change with every Livewire render; the map itself lives in the wire:ignore canvas.
--}}
@vite('resources/js/map.js')

<div
    data-map="explore"
    data-style-url="{{ config('avytra.map.style_url') }}"
    data-points="{{ json_encode(array_values($points), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
    data-centre-lat="{{ $centre['latitude'] }}"
    data-centre-lng="{{ $centre['longitude'] }}"
    data-zoom-country="{{ config('avytra.map.zoom.country') }}"
    data-zoom-exact="{{ config('avytra.map.zoom.exact') }}"
    x-data="{ init() { this.$dispatch('avytra:map-mount') }, destroy() { window.dispatchEvent(new CustomEvent('avytra:map-unmount', { detail: this.$el })) } }"
    {{ $attributes->class('flex flex-col gap-2') }}
>
    <div data-map-canvas hidden class="h-96 w-full overflow-hidden rounded-md border border-zinc-200 bg-mist" role="region" aria-label="{{ __('Map of the results') }}"></div>

    <div data-map-fallback class="flex flex-col gap-1 rounded-md border border-dashed border-zinc-300 bg-mist p-4 text-sm text-slate">
        <span class="font-semibold text-ink">{{ __('The map is not available in this browser.') }}</span>
        <span>{{ __('The interactive map needs WebGL. The results are still listed below.') }}</span>
    </div>

    <flux:text size="sm" class="text-slate">
        @if ($points === [])
            {{ __('No listing on this page has a public location.') }}
        @else
            {{ __('Only the listings on this page with a public location. Approximate areas are shown as circles.') }}
        @endif
    </flux:text>
</div>
