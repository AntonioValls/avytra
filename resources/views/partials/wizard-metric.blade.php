@php
    /** @var \App\Enums\FinancialMetric $metric */
    $key = $metric->value;
    $disclosure = $economics->metrics[$key]['disclosure'] ?? \App\Enums\Disclosure::Exact->value;
@endphp

{{-- One financial metric of step 4: figure(s) plus how it is disclosed. Shared by the featured and the folded groups. --}}
<div class="flex flex-col gap-3 rounded-md border border-zinc-200 p-4 dark:border-zinc-700" wire:key="metric-{{ $key }}">
    <div class="flex flex-col gap-1">
        <flux:label>{{ $metric->label() }}</flux:label>
        @if ($metric->description())
            <flux:description>{{ $metric->description() }}</flux:description>
        @endif
    </div>

    <div class="grid gap-3 sm:grid-cols-[1fr_1fr_12rem]">
        @if ($disclosure === \App\Enums\Disclosure::Range->value)
            <flux:input wire:model="economics.metrics.{{ $key }}.amount_min" type="number" min="0" step="1" :placeholder="__('Minimum')" />
            <flux:input wire:model="economics.metrics.{{ $key }}.amount_max" type="number" min="0" step="1" :placeholder="__('Maximum')" />
        @elseif ($disclosure === \App\Enums\Disclosure::OnRequest->value)
            <flux:text class="sm:col-span-2 self-center">{{ __('Buyers will see “On request”.') }}</flux:text>
        @else
            <flux:input wire:model="economics.metrics.{{ $key }}.amount" type="number" min="0" step="1" :placeholder="__('Amount in euros')" class="sm:col-span-2" />
        @endif

        <flux:select variant="listbox" wire:model.live="economics.metrics.{{ $key }}.disclosure" :aria-label="__('Disclosure')">
            @foreach (\App\Enums\Disclosure::cases() as $option)
                <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:error name="economics.metrics.{{ $key }}.amount" />
    <flux:error name="economics.metrics.{{ $key }}.amount_min" />
    <flux:error name="economics.metrics.{{ $key }}.amount_max" />
</div>
