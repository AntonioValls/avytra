@props([
    'text',
    'pending' => false,
    'size' => 'sm',
])

{{-- "Availability confirmed N days ago" on cards and listing pages (docs/13, "Público"). --}}
@if ($text)
    <flux:badge :size="$size" :color="$pending ? 'amber' : 'lime'" :icon="$pending ? 'clock' : 'check-badge'" {{ $attributes }}>{{ $text }}</flux:badge>
@endif
