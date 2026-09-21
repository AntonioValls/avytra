@props([
    'title' => null,
    'area' => 'app',
])

<x-layouts::app.sidebar :title="$title" :area="$area">
    <flux:main>
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
