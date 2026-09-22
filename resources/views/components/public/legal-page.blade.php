@props([
    'title',
    'routeName',
])

@php
    $meta = new \App\Support\Seo\PageMeta(title: $title, canonical: route($routeName));
@endphp

{{-- Shared frame of the legal pages. Texts are provisional until the owner supplies the final ones (docs/16 "Cumplimiento"). --}}
<x-layouts::public :meta="$meta">
    <section class="mx-auto flex max-w-3xl flex-col gap-8 px-6 py-16 lg:px-8">
        <h1 class="text-3xl font-black tracking-tight text-ink lg:text-4xl">{{ $title }}</h1>

        <flux:callout icon="pencil-square" variant="warning">
            <flux:callout.heading>{{ __('Provisional text') }}</flux:callout.heading>
            <flux:callout.text>{{ __('This page holds a draft. The final wording will be published before launch.') }}</flux:callout.text>
        </flux:callout>

        <div class="flex flex-col gap-6 text-base leading-7 text-ink">
            {{ $slot }}
        </div>
    </section>
</x-layouts::public>
