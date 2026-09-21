<x-layouts::app :title="__('Administration')" area="admin">
    <div class="flex flex-col gap-8">
        <div class="flex flex-col gap-1">
            <flux:heading size="xl" level="1">{{ __('Operational summary') }}</flux:heading>
            <flux:text>{{ __('Administration') }}</flux:text>
        </div>

        <flux:callout icon="information-circle" variant="secondary">
            <flux:callout.text>{{ __('Users, businesses, listings and reports will be managed from here. Nothing to show yet.') }}</flux:callout.text>
        </flux:callout>
    </div>
</x-layouts::app>
