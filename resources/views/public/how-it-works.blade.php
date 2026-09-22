@php
    $meta = new \App\Support\Seo\PageMeta(
        title: __('How it works'),
        description: __('How AVYTRA works for buyers and sellers: explore, read the listing, contact the owner. Publish, confirm availability, change hands.'),
        canonical: route('how-it-works'),
    );
@endphp

<x-layouts::public :meta="$meta">
    <section class="mx-auto flex max-w-3xl flex-col gap-10 px-6 py-16 lg:px-8">
        <div class="flex flex-col gap-3">
            <h1 class="text-3xl font-black tracking-tight text-ink lg:text-4xl">{{ __('How it works') }}</h1>
            <flux:text class="text-lg">{{ __('AVYTRA is a free marketplace for businesses that change hands. It connects the person who built a business with the person ready to continue it. It does not intermediate, does not charge and does not process payments.') }}</flux:text>
        </div>

        <div class="flex flex-col gap-4">
            <flux:heading size="xl" level="2">{{ __('If you are buying') }}</flux:heading>
            <flux:text>{{ __('Explore by sector, province, type of business or operation. Every listing shows the price (or "on request"), the figures the seller chose to share, what is included, the reason for sale and an approximate location. When you want to know more, you contact the owner directly through the channels they chose.') }}</flux:text>
            <flux:text>{{ __('Availability is confirmed: sellers confirm every few weeks that the business is still for sale, and listings without confirmation are paused automatically.') }}</flux:text>
        </div>

        <div class="flex flex-col gap-4">
            <flux:heading size="xl" level="2">{{ __('If you are selling') }}</flux:heading>
            <flux:text>{{ __('Create an account, describe the business in a short step-by-step form and publish. It is visible immediately. You choose what is public: the location can be approximate or hidden, every figure has its own disclosure and your account data never appears.') }}</flux:text>
            <flux:text>{{ __('You can pause, edit, mark as sold or withdraw the listing whenever you want. Nothing is ever deleted for inactivity.') }}</flux:text>
        </div>

        <div class="flex flex-col gap-4">
            <flux:heading size="xl" level="2">{{ __('What AVYTRA does not do') }}</flux:heading>
            <flux:text>{{ __('It does not value businesses, does not verify the figures declared by sellers and does not take part in negotiations. Check everything before any decision and rely on your own advisors.') }}</flux:text>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
            <flux:button variant="primary" :href="route('listings.index')" wire:navigate icon-trailing="arrow-right">{{ __('Explore businesses') }}</flux:button>
            <flux:button :href="route('publish.landing')" wire:navigate>{{ __('Sell your business') }}</flux:button>
        </div>
    </section>
</x-layouts::public>
