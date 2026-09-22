@props([
    'listing',
    'size' => 'sm',
])

@php
    /** @var \App\Models\Listing $listing */
    $needsConfirmation = $listing->needsConfirmation();
@endphp

{{-- Status badge of the panel (docs/06, "Vista del usuario"): "needs confirmation" is a condition, not a status. --}}
@if ($needsConfirmation)
    <flux:badge :size="$size" color="amber" icon="clock" {{ $attributes }}>{{ __('Needs confirmation') }}</flux:badge>
@else
    <flux:badge :size="$size" :color="$listing->status->badgeColor()" {{ $attributes }}>{{ $listing->status->label() }}</flux:badge>
@endif
