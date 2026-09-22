@php
    $meta = new \App\Support\Seo\PageMeta(
        title: __('Sell or transfer your business for free'),
        description: __('Publish your business on AVYTRA in a few steps. Free, with control over what is public and with confirmed availability.'),
        canonical: route('publish.landing'),
    );
    $supportEmail = config('avytra.support.email');
    $supportPhone = config('avytra.support.phone');
    $ctaRoute = auth()->check() ? route('panel.listings.create') : route('register');
@endphp

{{-- Landing "Publicar" (docs/08): free, wizard steps, privacy, confirmed availability, assisted publishing. --}}
<x-layouts::public :meta="$meta">
    <section class="bg-mist">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 px-6 py-16 lg:px-8 lg:py-24">
            <span class="text-xs font-bold uppercase tracking-wider text-slate">{{ __('Sell your business') }}</span>
            <h1 class="max-w-3xl text-4xl font-black leading-tight tracking-tight text-ink lg:text-6xl">{{ __('Your business can have a next chapter.') }}</h1>
            <p class="max-w-2xl text-lg leading-8 text-slate">{{ __('Publish it on AVYTRA for free. You decide what is public, buyers contact you directly and nothing is charged, now or later.') }}</p>
            <div class="flex flex-col gap-3 sm:flex-row">
                <flux:button variant="primary" :href="$ctaRoute" wire:navigate icon-trailing="arrow-right">{{ __('Publish a business') }}</flux:button>
                <flux:button :href="route('how-it-works')" wire:navigate>{{ __('How it works') }}</flux:button>
            </div>
        </div>
    </section>

    <section class="mx-auto flex max-w-7xl flex-col gap-10 px-6 py-16 lg:px-8">
        <flux:heading size="xl" level="2">{{ __('Eight short steps, one draft you can resume') }}</flux:heading>
        <ol class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                __('Operation') => __('What you offer: full sale, transfer, partner entry…'),
                __('Business') => __('Name, sector, description. The name can be generic if you prefer.'),
                __('Characteristics') => __('Reason for sale, highlights and what is included.'),
                __('Figures') => __('Price and figures, each with its own disclosure: exact, range, on request or hidden.'),
                __('Location') => __('Province and municipality. The exact address is never shown unless you want it.'),
                __('Contact') => __('The channels you choose. Nothing from your account is published.'),
                __('Images') => __('Cover and gallery (coming soon).'),
                __('Publish') => __('Review and publish. Visible immediately.'),
            ] as $step => $text)
                <li class="flex flex-col gap-2 rounded-md border border-zinc-200 p-5">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate">{{ __('Step :n', ['n' => $loop->iteration]) }}</span>
                    <flux:heading>{{ $step }}</flux:heading>
                    <flux:text size="sm">{{ $text }}</flux:text>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="bg-mist">
        <div class="mx-auto grid max-w-7xl gap-8 px-6 py-16 md:grid-cols-3 lg:px-8">
            <div class="flex flex-col gap-3">
                <flux:icon.banknotes class="size-8 text-transfer" />
                <flux:heading size="lg">{{ __('Free, without surprises') }}</flux:heading>
                <flux:text>{{ __('No fees, no commissions, no featured plans. AVYTRA is a showcase and a meeting point.') }}</flux:text>
            </div>
            <div class="flex flex-col gap-3">
                <flux:icon.lock-closed class="size-8 text-transfer" />
                <flux:heading size="lg">{{ __('Privacy by design') }}</flux:heading>
                <flux:text>{{ __('Approximate location by default, figures you can keep private, website hidden until you talk. Your account email and phone are never public.') }}</flux:text>
            </div>
            <div class="flex flex-col gap-3">
                <flux:icon.check-badge class="size-8 text-transfer" />
                <flux:heading size="lg">{{ __('Confirmed availability') }}</flux:heading>
                <flux:text>{{ __('Every :days days we ask you with one click whether the business is still available. If you do not answer, the listing is paused, never deleted.', ['days' => config('avytra.freshness.confirmation_period_days')]) }}</flux:text>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
        <div class="flex flex-col gap-6 rounded-lg border border-zinc-200 p-8 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-col gap-2">
                <flux:heading size="xl" level="2">{{ __('Would you rather we do it for you?') }}</flux:heading>
                <flux:text>{{ __('If you are not comfortable with online forms, contact us and we will publish the business on your behalf. You keep full control of the listing.') }}</flux:text>
            </div>
            <div class="flex flex-col gap-2 text-sm">
                @if ($supportPhone)
                    <flux:button :href="'tel:'.$supportPhone" icon="phone">{{ $supportPhone }}</flux:button>
                @endif
                @if ($supportEmail)
                    <flux:button :href="'mailto:'.$supportEmail" icon="envelope" variant="ghost">{{ $supportEmail }}</flux:button>
                @endif
                @if (! $supportPhone && ! $supportEmail)
                    <flux:text class="text-slate">{{ __('Contact details will be available soon.') }}</flux:text>
                @endif
            </div>
        </div>
    </section>
</x-layouts::public>
