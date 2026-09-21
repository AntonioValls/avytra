<x-layouts::app :title="__('Panel')">
    <div class="flex flex-col gap-8">
        <div class="flex flex-col gap-1">
            <flux:heading size="xl" level="1">{{ __('Hello, :name', ['name' => auth()->user()->name]) }}</flux:heading>
            <flux:text>{{ __('Panel') }}</flux:text>
        </div>

        {{-- Empty state: businesses and listings arrive in Phases 2 and 3. --}}
        <div class="flex flex-col items-center gap-4 rounded-lg border border-dashed border-zinc-300 bg-mist px-6 py-16 text-center dark:border-zinc-700 dark:bg-zinc-900">
            <x-app-logo-icon class="size-12 text-ink dark:text-white" />
            <flux:heading size="lg">{{ __('You do not have any businesses yet.') }}</flux:heading>
            <flux:text class="max-w-md">{{ __('When you create your first business it will appear here, together with its listings and what you need to do to keep them up to date.') }}</flux:text>
            <flux:button variant="primary" disabled>{{ __('Creating businesses will be available soon.') }}</flux:button>
        </div>
    </div>
</x-layouts::app>
