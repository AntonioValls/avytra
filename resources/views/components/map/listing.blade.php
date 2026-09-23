@props([
    'listing',
])

@php
    /** @var \App\Support\Listings\PublicListingPresenter $listing */
    $point = $listing->publicPoint();
    $hasPoint = $point !== null && $point->latitude !== null && $point->longitude !== null;
    $isExact = $hasPoint && $point->radiusM === null;
    $zoom = (int) config($isExact ? 'avytra.map.zoom.exact' : 'avytra.map.zoom.municipality');
    $osmUrl = $hasPoint
        ? ($isExact
            ? sprintf('https://www.openstreetmap.org/?mlat=%s&mlon=%s#map=%d/%s/%s', $point->latitude, $point->longitude, $zoom, $point->latitude, $point->longitude)
            : sprintf('https://www.openstreetmap.org/#map=%d/%s/%s', $zoom, $point->latitude, $point->longitude))
        : null;
@endphp

{{--
    Public map of a listing (docs/10 "Render según visibilidad"). Reads only the derived public
    point: a pin for "exact", a circle for "approximate" and "municipality only", nothing for
    "hidden" (the caller shows the province text instead). The fallback block is what visitors
    without WebGL (or JavaScript) see; resources/js/map.js swaps it for the map when it can.
--}}
@if ($hasPoint)
    @vite('resources/js/map.js')

    <div
        data-map="listing"
        data-style-url="{{ config('avytra.map.style_url') }}"
        data-lat="{{ $point->latitude }}"
        data-lng="{{ $point->longitude }}"
        data-radius="{{ $point->radiusM ?? '' }}"
        data-zoom="{{ $zoom }}"
        data-label="{{ $isExact ? ($listing->addressLine() ?? $listing->locationText()) : $listing->locationText() }}"
        x-data="{ init() { this.$dispatch('avytra:map-mount') }, destroy() { window.dispatchEvent(new CustomEvent('avytra:map-unmount', { detail: this.$el })) } }"
        {{ $attributes->class('flex flex-col gap-2') }}
    >
        <div data-map-canvas hidden class="aspect-[16/9] w-full overflow-hidden rounded-md border border-zinc-200 bg-mist sm:aspect-[21/9]" role="region" aria-label="{{ __('Location map') }}"></div>

        <div data-map-fallback class="flex flex-col gap-2 rounded-md border border-dashed border-zinc-300 bg-mist p-4 text-sm text-slate">
            <span class="inline-flex items-center gap-2 font-semibold text-ink"><flux:icon.map variant="mini" class="text-transfer" /> {{ $listing->locationText() }}</span>
            <span>{{ __('The interactive map needs WebGL, which your browser does not provide.') }}</span>
            <a href="{{ $osmUrl }}" target="_blank" rel="noopener nofollow" class="font-semibold text-transfer hover:underline">{{ $isExact ? __('See the location on OpenStreetMap') : __('See the area on OpenStreetMap') }}</a>
        </div>
    </div>
@endif
