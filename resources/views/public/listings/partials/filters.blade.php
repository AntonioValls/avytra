{{-- Filter controls shared by the desktop sidebar and the mobile flyout of the explore page. --}}
<flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" :label="__('Search')" :placeholder="__('Title or business name')" clearable />

@if ($fixedCategoryId === null)
    <flux:select variant="listbox" wire:model.live="sector" :label="__('Sector')" :placeholder="__('Any sector')" searchable clearable>
        @foreach ($this->categories as $category)
            <flux:select.option :value="$category->slug">{{ $category->name }}</flux:select.option>
        @endforeach
    </flux:select>
@endif

@if (! $onlineOnly)
    <flux:radio.group wire:model.live="type" variant="segmented" :label="__('Type of business')" size="sm">
        <flux:radio value="">{{ __('All') }}</flux:radio>
        <flux:radio value="fisico">{{ __('Premises') }}</flux:radio>
        <flux:radio value="online">{{ __('Online') }}</flux:radio>
        <flux:radio value="hibrido">{{ __('Hybrid') }}</flux:radio>
    </flux:radio.group>
@endif

<flux:select variant="listbox" wire:model.live="operation" :label="__('Operation')" :placeholder="__('Any operation')" clearable>
    @foreach (\App\Enums\OperationType::cases() as $operationType)
        <flux:select.option :value="$operationType->value">{{ $operationType->label() }}</flux:select.option>
    @endforeach
</flux:select>

@if ($fixedProvinceId === null && ! $onlineOnly)
    <flux:select variant="listbox" wire:model.live="province" :label="__('Province')" :placeholder="__('Any province')" searchable clearable>
        @foreach ($this->provinces as $provinceOption)
            <flux:select.option :value="$provinceOption['slug']">{{ $provinceOption['name'] }}</flux:select.option>
        @endforeach
    </flux:select>
@endif

<flux:field>
    <flux:label>{{ __('Price (EUR)') }}</flux:label>
    <div class="grid grid-cols-2 gap-2">
        <flux:input type="number" inputmode="numeric" min="0" step="1000" wire:model.live.debounce.500ms="priceMin" :placeholder="__('Min')" :aria-label="__('Minimum price')" />
        <flux:input type="number" inputmode="numeric" min="0" step="1000" wire:model.live.debounce.500ms="priceMax" :placeholder="__('Max')" :aria-label="__('Maximum price')" />
    </div>
    <flux:description>{{ __('Listings with price on request are left out when you filter by price.') }}</flux:description>
</flux:field>

@if ($this->activeFilterCount > 0)
    <flux:button variant="ghost" size="sm" icon="x-mark" wire:click="clearFilters">{{ __('Clear filters') }}</flux:button>
@endif
