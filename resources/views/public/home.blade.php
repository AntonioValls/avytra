@php
    $meta = \App\Support\Seo\PageMeta::make(
        title: __('Businesses that change hands.'),
        description: __('Businesses for sale or transfer, with clear data and confirmed availability.'),
    );
@endphp

{{-- Provisional home (Phase 1). The real home with search and listings arrives in Phase 4. --}}
<x-layouts::public :meta="$meta">
    <section class="bg-mist">
        <div class="mx-auto flex max-w-7xl flex-col gap-8 px-6 py-20 lg:px-8 lg:py-28">
            <span class="text-xs font-bold uppercase tracking-wider text-slate">{{ __('Buy. Sell. Continue.') }}</span>
            <h1 class="max-w-3xl text-4xl font-black leading-tight tracking-tight text-ink lg:text-6xl">
                {{ __('Find a business that is already up and running.') }}
            </h1>
            <p class="max-w-2xl text-lg leading-8 text-slate">
                {{ __('AVYTRA is a free platform to discover, publish, sell and transfer businesses. Listings that nobody confirms are paused automatically, so what you see is available.') }}
            </p>
            <div class="flex flex-col gap-3 sm:flex-row">
                @auth
                    <flux:button variant="primary" :href="route('dashboard')" wire:navigate icon-trailing="arrow-right">{{ __('Go to panel') }}</flux:button>
                @else
                    <flux:button variant="primary" :href="route('register')" wire:navigate icon-trailing="arrow-right">{{ __('Publish a business') }}</flux:button>
                    <flux:button :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:button>
                @endauth
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-6 py-20 lg:px-8">
        <div class="grid gap-8 md:grid-cols-3">
            <div class="flex flex-col gap-3 rounded-lg border border-zinc-200 p-8">
                <flux:icon.check-badge class="size-8 text-transfer" />
                <flux:heading size="lg">{{ __('Confirmed availability') }}</flux:heading>
                <flux:text>{{ __('Every listing shows when its owner last confirmed it is still available. Listings without confirmation are paused, never deleted.') }}</flux:text>
            </div>
            <div class="flex flex-col gap-3 rounded-lg border border-zinc-200 p-8">
                <flux:icon.chart-bar class="size-8 text-transfer" />
                <flux:heading size="lg">{{ __('Clear data before contacting') }}</flux:heading>
                <flux:text>{{ __('Price, revenue, what is included, approximate location and how to contact the owner, in one structured page.') }}</flux:text>
            </div>
            <div class="flex flex-col gap-3 rounded-lg border border-zinc-200 p-8">
                <flux:icon.pencil-square class="size-8 text-transfer" />
                <flux:heading size="lg">{{ __('Publishing is easy') }}</flux:heading>
                <flux:text>{{ __('A short step-by-step form, drafts you can resume later and help by phone if you need it.') }}</flux:text>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-6 lg:px-8">
        <flux:callout icon="clock" variant="secondary">
            <flux:callout.heading>{{ __('Coming soon') }}</flux:callout.heading>
            <flux:callout.text>{{ __('We are building the marketplace. If you have a business to sell or transfer, create your account and we will let you know when you can publish.') }}</flux:callout.text>
        </flux:callout>
    </section>
</x-layouts::public>
