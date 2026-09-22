@props([
    'heading',
    'text' => null,
    'icon' => null,
])

{{-- Empty state for lists and panels: brand symbol (or a Heroicon), heading, explanation and actions in the slot. --}}
<div {{ $attributes->class('flex flex-col items-center gap-4 rounded-lg border border-dashed border-zinc-300 bg-mist px-6 py-16 text-center dark:border-zinc-700 dark:bg-zinc-900') }}>
    @if ($icon)
        <flux:icon :icon="$icon" class="size-10 text-slate" />
    @else
        <x-app-logo-icon class="size-12 text-ink dark:text-white" />
    @endif

    <flux:heading size="lg">{{ $heading }}</flux:heading>

    @if ($text)
        <flux:text class="max-w-md">{{ $text }}</flux:text>
    @endif

    @if ($slot->isNotEmpty())
        <div class="mt-2 flex flex-col gap-3 sm:flex-row">
            {{ $slot }}
        </div>
    @endif
</div>
