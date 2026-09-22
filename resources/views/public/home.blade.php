{{-- Home (docs/08): hero with search, quick links, latest listings, how it works, trust, final CTA. --}}
<x-layouts::public :meta="$meta">
    <section class="bg-mist">
        <div class="mx-auto flex max-w-7xl flex-col gap-8 px-6 py-16 lg:px-8 lg:py-24">
            <span class="text-xs font-bold uppercase tracking-wider text-slate">{{ __('Buy. Sell. Continue.') }}</span>
            <h1 class="max-w-3xl text-4xl font-black leading-tight tracking-tight text-ink lg:text-6xl">
                {{ __('Find a business that is already up and running.') }}
            </h1>
            <p class="max-w-2xl text-lg leading-8 text-slate">
                {{ __('Businesses for sale or transfer, with clear data and confirmed availability.') }}
            </p>

            <form method="GET" action="{{ route('listings.index') }}" class="flex w-full max-w-3xl flex-col gap-3 rounded-lg border border-zinc-200 bg-white p-3 shadow-sm sm:flex-row sm:items-center">
                <flux:input name="q" icon="magnifying-glass" :placeholder="__('Sector, activity or business name')" class="flex-1" :aria-label="__('Search')" />
                <flux:select variant="listbox" name="provincia" :placeholder="__('Any province')" searchable clearable class="sm:w-56" :aria-label="__('Province')">
                    @foreach ($provinces as $province)
                        <flux:select.option :value="$province['slug']">{{ $province['name'] }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:button type="submit" variant="primary" icon-trailing="arrow-right">{{ __('Search') }}</flux:button>
            </form>

            <div class="flex flex-wrap items-center gap-2">
                <span class="me-1 text-sm text-slate">{{ __('Quick links:') }}</span>
                @foreach ($topCategories as $category)
                    <flux:badge :href="route('categories.show', $category['slug'])" wire:navigate size="sm" color="zinc" class="hover:bg-zinc-200">{{ $category['name'] }}</flux:badge>
                @endforeach
                <flux:badge :href="route('listings.online')" wire:navigate size="sm" color="blue">{{ __('Online businesses') }}@if ($onlineCount > 0) · {{ $onlineCount }}@endif</flux:badge>
                <flux:badge :href="route('listings.index', ['operacion' => \App\Enums\OperationType::Transfer->value])" wire:navigate size="sm" color="indigo">{{ __('Transfers') }}</flux:badge>
                @foreach ($topProvinces as $province)
                    <flux:badge :href="route('provinces.show', $province['slug'])" wire:navigate size="sm" color="zinc" class="hover:bg-zinc-200">{{ $province['name'] }}</flux:badge>
                @endforeach
            </div>

            <div class="flex flex-col gap-3 sm:flex-row">
                @auth
                    <flux:button :href="route('dashboard')" wire:navigate icon-trailing="arrow-right">{{ __('Go to panel') }}</flux:button>
                @else
                    <flux:button :href="route('publish.landing')" wire:navigate icon-trailing="arrow-right">{{ __('Sell your business') }}</flux:button>
                @endauth
            </div>
        </div>
    </section>

    <section class="mx-auto flex max-w-7xl flex-col gap-8 px-6 py-16 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-col gap-1">
                <flux:heading size="xl" level="2">{{ __('Latest listings') }}</flux:heading>
                <flux:text>{{ __('Businesses published recently, all with their availability confirmed by the seller.') }}</flux:text>
            </div>
            <flux:button variant="ghost" :href="route('listings.index')" wire:navigate icon-trailing="arrow-right">{{ __('See all') }}</flux:button>
        </div>

        @if ($latest->isEmpty())
            <x-empty-state :heading="__('The first listings are on their way.')" :text="__('AVYTRA has just opened. If you have a business to sell or transfer, yours can be the first.')">
                <flux:button variant="primary" :href="route('publish.landing')" wire:navigate icon-trailing="arrow-right">{{ __('Publish a business') }}</flux:button>
            </x-empty-state>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($latest as $card)
                    <x-listing-card :listing="$card" />
                @endforeach
            </div>
        @endif
    </section>

    <section class="bg-mist">
        <div class="mx-auto flex max-w-7xl flex-col gap-10 px-6 py-16 lg:px-8">
            <flux:heading size="xl" level="2">{{ __('How it works') }}</flux:heading>

            <div class="grid gap-10 lg:grid-cols-2">
                <div class="flex flex-col gap-5">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate">{{ __('If you are buying') }}</span>
                    <ol class="flex flex-col gap-4">
                        <li class="flex gap-4"><span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-ink text-sm font-bold text-lime">1</span><div><flux:heading>{{ __('Explore by sector, province or type') }}</flux:heading><flux:text>{{ __('Filters you can share by URL and cards with the data that matters.') }}</flux:text></div></li>
                        <li class="flex gap-4"><span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-ink text-sm font-bold text-lime">2</span><div><flux:heading>{{ __('Read the listing') }}</flux:heading><flux:text>{{ __('Price, figures, what is included, reason for sale and approximate location.') }}</flux:text></div></li>
                        <li class="flex gap-4"><span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-ink text-sm font-bold text-lime">3</span><div><flux:heading>{{ __('Contact the owner directly') }}</flux:heading><flux:text>{{ __('No intermediaries, no fees. You talk to whoever built the business.') }}</flux:text></div></li>
                    </ol>
                </div>
                <div class="flex flex-col gap-5">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate">{{ __('If you are selling') }}</span>
                    <ol class="flex flex-col gap-4">
                        <li class="flex gap-4"><span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-ink text-sm font-bold text-lime">1</span><div><flux:heading>{{ __('Create your account and describe the business') }}</flux:heading><flux:text>{{ __('A short step-by-step form. You decide what is public and what stays private.') }}</flux:text></div></li>
                        <li class="flex gap-4"><span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-ink text-sm font-bold text-lime">2</span><div><flux:heading>{{ __('Publish for free') }}</flux:heading><flux:text>{{ __('Your listing is visible immediately, with the contact channels you choose.') }}</flux:text></div></li>
                        <li class="flex gap-4"><span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-ink text-sm font-bold text-lime">3</span><div><flux:heading>{{ __('Confirm it is still available') }}</flux:heading><flux:text>{{ __('Every few weeks we ask with one click. That keeps the marketplace reliable.') }}</flux:text></div></li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
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
                <flux:icon.lock-closed class="size-8 text-transfer" />
                <flux:heading size="lg">{{ __('Privacy by design') }}</flux:heading>
                <flux:text>{{ __('Approximate location, figures you can keep private and no personal data of your account on the public page.') }}</flux:text>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-6 pb-8 lg:px-8">
        <div class="flex flex-col gap-6 rounded-lg bg-ink px-8 py-12 text-white lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-col gap-2">
                <flux:heading size="xl" class="!text-white">{{ __('What if your next business already exists?') }}</flux:heading>
                <p class="text-zinc-300">{{ __('Your business can have a next chapter, too.') }}</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row">
                <flux:button variant="primary" :href="route('listings.index')" wire:navigate icon-trailing="arrow-right">{{ __('Explore businesses') }}</flux:button>
                <flux:button variant="filled" :href="route('publish.landing')" wire:navigate>{{ __('Sell your business') }}</flux:button>
            </div>
        </div>
    </section>
</x-layouts::public>
