@props([
    'text',
    'negotiable' => false,
    'size' => 'md',
])

{{-- Asking price as the seller chose to disclose it. Callers format it with PriceFormatter (or the public presenter). --}}
<span {{ $attributes->class(['inline-flex flex-wrap items-baseline gap-x-2', 'text-lg font-bold text-ink dark:text-white' => $size === 'md', 'text-2xl font-black text-ink dark:text-white' => $size === 'lg', 'text-sm font-semibold text-ink dark:text-white' => $size === 'sm']) }}>
    <span>{{ $text }}</span>
    @if ($negotiable)
        <span class="text-xs font-medium text-slate">{{ __('Negotiable') }}</span>
    @endif
</span>
