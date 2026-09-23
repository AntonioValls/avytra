@props([
    'latitude' => null,
    'longitude' => null,
    'centre' => null,
    'model' => 'location',
])

@php
    /** @var \App\Support\Location\Coordinates|null $centre */
    $default = config('avytra.map.default_centre');
    $hasPoint = $latitude !== null && $longitude !== null;
@endphp

{{--
    Location picker for the business form and wizard step 5 (docs/10 "Cómo obtiene el vendedor
    sus coordenadas"). The map centres on the chosen municipality; a click or a dragged marker
    writes the private coordinates into the two hidden inputs bound with wire:model. Only the
    owner (or the superadmin) ever sees this map, so the real point may appear here.
--}}
@vite('resources/js/map.js')

<div
    data-map="picker"
    data-style-url="{{ config('avytra.map.style_url') }}"
    data-lat="{{ $latitude ?? '' }}"
    data-lng="{{ $longitude ?? '' }}"
    data-centre-lat="{{ $centre?->latitude ?? '' }}"
    data-centre-lng="{{ $centre?->longitude ?? '' }}"
    data-default-lat="{{ $default['latitude'] }}"
    data-default-lng="{{ $default['longitude'] }}"
    data-zoom-country="{{ config('avytra.map.zoom.country') }}"
    data-zoom-municipality="{{ config('avytra.map.zoom.municipality') }}"
    data-zoom-exact="{{ config('avytra.map.zoom.exact') }}"
    x-data="{ init() { this.$dispatch('avytra:map-mount') }, destroy() { window.dispatchEvent(new CustomEvent('avytra:map-unmount', { detail: this.$el })) } }"
    {{ $attributes->class('flex flex-col gap-3') }}
>
    <input type="hidden" wire:model.live="{{ $model }}.latitude" data-map-input="lat" />
    <input type="hidden" wire:model.live="{{ $model }}.longitude" data-map-input="lng" />

    <div data-map-canvas hidden class="h-80 w-full overflow-hidden rounded-md border border-zinc-200 bg-mist" role="application" aria-label="{{ __('Map to place the premises') }}"></div>

    <div data-map-fallback class="flex flex-col gap-1 rounded-md border border-dashed border-zinc-300 bg-mist p-4 text-sm text-slate">
        <span class="font-semibold text-ink">{{ __('The map is not available in this browser.') }}</span>
        <span>{{ __('Without a pin, the public location will be the municipality area. You can place the point later from another device.') }}</span>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-2 text-sm text-slate">
        <span>
            @if ($hasPoint)
                {{ __('Drag the marker to adjust the point. Only you can see the exact location here.') }}
            @else
                {{ __('Click on the map to place the premises. Only you can see the exact location here.') }}
            @endif
        </span>

        @if ($hasPoint)
            <flux:button type="button" size="sm" variant="ghost" icon="x-mark" wire:click="clearLocationPoint">{{ __('Remove the point') }}</flux:button>
        @endif
    </div>
</div>
