@props([
    'meta' => null,
])

@php
    /** @var \App\Support\Seo\PageMeta $meta */
    $meta ??= \App\Support\Seo\PageMeta::make();
@endphp

{{-- Public marketplace layout. Always light: the brand is luminous and visitors have no appearance setting. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />

        <title>{{ $meta->fullTitle() }}</title>
        <meta name="description" content="{{ $meta->resolvedDescription() }}">
        <link rel="canonical" href="{{ $meta->resolvedCanonical() }}">
        @if ($meta->robots)
            <meta name="robots" content="{{ $meta->robots }}">
        @endif

        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:locale" content="es_ES">
        <meta property="og:type" content="{{ $meta->ogType }}">
        <meta property="og:title" content="{{ $meta->fullTitle() }}">
        <meta property="og:description" content="{{ $meta->resolvedDescription() }}">
        <meta property="og:url" content="{{ $meta->resolvedCanonical() }}">
        <meta property="og:image" content="{{ $meta->resolvedOgImage() }}">
        <meta property="og:image:width" content="{{ config('avytra.media.conversions.og.width') }}">
        <meta property="og:image:height" content="{{ config('avytra.media.conversions.og.height') }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $meta->fullTitle() }}">
        <meta name="twitter:description" content="{{ $meta->resolvedDescription() }}">
        <meta name="twitter:image" content="{{ $meta->resolvedOgImage() }}">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-white text-ink antialiased">
        <x-public.header />

        <main>
            {{ $slot }}
        </main>

        <x-public.footer />

        {{-- JSON-LD goes in the body on purpose: wire:navigate keeps <head> scripts across navigations. --}}
        @foreach ($meta->jsonLd as $jsonLd)
            <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
        @endforeach

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
