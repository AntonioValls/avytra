@php
    $supportEmail = config('avytra.support.email');
@endphp

<footer class="mt-24 bg-ink text-white">
    <div class="mx-auto flex max-w-7xl flex-col gap-10 px-6 py-14 lg:flex-row lg:items-start lg:justify-between lg:px-8">
        <div class="flex flex-col gap-4">
            <x-app-logo class="text-white" wire:navigate />
            <p class="max-w-sm text-sm leading-6 text-zinc-300">{{ __('Businesses that change hands.') }}</p>
        </div>

        <div class="flex flex-col gap-3 text-sm text-zinc-300">
            <span class="text-xs font-bold uppercase tracking-wider text-zinc-400">{{ __('Contact') }}</span>
            @if ($supportEmail)
                <a href="mailto:{{ $supportEmail }}" class="hover:text-white">{{ $supportEmail }}</a>
            @endif
            <span class="text-zinc-500">© {{ now()->year }} {{ config('app.name') }}. {{ __('All rights reserved.') }}</span>
        </div>
    </div>
</footer>
