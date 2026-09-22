@php
    $meta = new \App\Support\Seo\PageMeta(title: __('Listing no longer available'), robots: 'noindex');
@endphp

{{-- 410 Gone: archived or deleted listings that once had a public URL (docs/15). --}}
<x-layouts::public :meta="$meta">
    <section class="mx-auto flex max-w-3xl flex-col gap-8 px-6 py-24 lg:px-8">
        <x-empty-state :heading="__('This listing is no longer available.')" :text="__('Its owner withdrew it. There are other businesses waiting for a next chapter.')">
            <flux:button variant="primary" :href="route('listings.index')" wire:navigate icon="magnifying-glass">{{ __('Explore businesses') }}</flux:button>
            <flux:button :href="route('home')" wire:navigate>{{ __('Go to home') }}</flux:button>
        </x-empty-state>
    </section>
</x-layouts::public>
