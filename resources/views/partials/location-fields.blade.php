{{--
    Premises fields shared by the business form and wizard step 5. Expects a component that
    uses App\Livewire\Concerns\ManagesLocationPicker with $location (LocationForm),
    $this->provinces and $this->municipalities.
--}}
<div class="grid gap-6 sm:grid-cols-2">
    <flux:select wire:model.live="location.province_id" variant="listbox" searchable :label="__('Province')" :placeholder="__('Choose a province')">
        @foreach ($this->provinces as $province)
            <flux:select.option :value="$province->id" wire:key="province-{{ $province->id }}">{{ $province->name }}</flux:select.option>
        @endforeach
    </flux:select>

    <flux:select
        wire:model.live="location.municipality_id"
        variant="listbox"
        searchable
        :label="__('Municipality')"
        :placeholder="$this->municipalities->isEmpty() ? __('Choose a province first') : __('Choose a municipality')"
        :disabled="$this->municipalities->isEmpty()"
    >
        @foreach ($this->municipalities as $municipality)
            <flux:select.option :value="$municipality->id" wire:key="municipality-{{ $municipality->id }}">{{ $municipality->name }}</flux:select.option>
        @endforeach
    </flux:select>
</div>

<div class="grid gap-6 sm:grid-cols-[1fr_10rem]">
    <flux:input wire:model="location.address_line" :label="__('Address')" :badge="__('Private')" :description="__('Shown only if you choose “Exact address”.')" maxlength="255" />
    <flux:input wire:model="location.postal_code" :label="__('Postal code')" :badge="__('Private')" maxlength="10" />
</div>

<div class="flex flex-col gap-2">
    <flux:label>{{ __('Point on the map') }}</flux:label>
    <flux:description>{{ __('Choose the municipality first; then place the premises on the map so buyers see the area you decide below.') }}</flux:description>

    @if ($this->geocoderAvailable)
        <div>
            <flux:button type="button" size="sm" icon="magnifying-glass" wire:click="searchAddress" wire:loading.attr="disabled" wire:target="searchAddress">{{ __('Find the address on the map') }}</flux:button>
        </div>
    @endif

    <x-map.picker :latitude="$location->latitude" :longitude="$location->longitude" :centre="$this->municipalityCentre" />
</div>

<flux:radio.group wire:model.live="location.location_visibility" :label="__('Location visibility')">
    @foreach (\App\Enums\LocationVisibility::cases() as $visibility)
        <flux:radio :value="$visibility->value" :label="$visibility->label()" :description="$visibility->description()" />
    @endforeach
</flux:radio.group>

@if ($location->willFallBackToMunicipality())
    <flux:callout icon="map-pin" variant="warning">
        <flux:callout.text>{{ __('Without a point on the map, the location is published as “Municipality only”. Place the pin to use the visibility you chose.') }}</flux:callout.text>
    </flux:callout>
@endif
