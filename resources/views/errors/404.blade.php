@php
    $meta = new \App\Support\Seo\PageMeta(title: __('Page not found'), robots: 'noindex');
@endphp

<x-layouts::public :meta="$meta">
    <section class="mx-auto flex max-w-3xl flex-col gap-8 px-6 py-24 lg:px-8">
        <x-empty-state :heading="__('We could not find that page.')" :text="__('The listing may have been withdrawn or the address may be wrong. Search for other businesses instead.')">
            <flux:button variant="primary" :href="route('listings.index')" wire:navigate icon="magnifying-glass">{{ __('Explore businesses') }}</flux:button>
            <flux:button :href="route('home')" wire:navigate>{{ __('Go to home') }}</flux:button>
        </x-empty-state>
    </section>
</x-layouts::public>
