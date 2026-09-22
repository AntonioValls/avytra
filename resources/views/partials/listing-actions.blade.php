@php
    /** @var \App\Models\Listing $listing */
    $status = $listing->status;
    $editRoute = ($admin ?? false) ? 'admin.listings.edit' : 'listings.edit';
@endphp

{{-- Actions of a listing in the panel, by status (docs/09-dashboard-and-admin.md). Every action re-authorizes server side. --}}
<flux:dropdown position="bottom" align="end">
    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" :aria-label="__('Actions')" />
    <flux:menu>
        @if ($status === \App\Enums\ListingStatus::Draft)
            <flux:menu.item icon="pencil-square" :href="route($editRoute, $listing)" wire:navigate>{{ __('Continue') }}</flux:menu.item>
            <flux:menu.separator />
            <flux:menu.item icon="trash" variant="danger" wire:click="openConfirmation('delete', {{ $listing->id }})">{{ __('Delete draft') }}</flux:menu.item>
        @elseif ($status === \App\Enums\ListingStatus::Published)
            <flux:menu.item icon="check-badge" wire:click="confirmAvailability({{ $listing->id }})">{{ __('Still available') }}</flux:menu.item>
            <flux:menu.item icon="pencil-square" :href="route($editRoute, $listing)" wire:navigate>{{ __('Edit') }}</flux:menu.item>
            <flux:menu.item icon="pause" wire:click="pause({{ $listing->id }})">{{ __('Pause') }}</flux:menu.item>
            <flux:menu.separator />
            <flux:menu.item icon="hand-thumb-up" wire:click="openConfirmation('sold', {{ $listing->id }})">{{ __('Mark as sold') }}</flux:menu.item>
            <flux:menu.item icon="archive-box" wire:click="openConfirmation('archive', {{ $listing->id }})">{{ __('Archive') }}</flux:menu.item>
        @elseif ($status === \App\Enums\ListingStatus::Paused || $status === \App\Enums\ListingStatus::Expired)
            <flux:menu.item icon="play" wire:click="resume({{ $listing->id }})">{{ __('Resume') }}</flux:menu.item>
            <flux:menu.item icon="pencil-square" :href="route($editRoute, $listing)" wire:navigate>{{ __('Edit') }}</flux:menu.item>
            <flux:menu.separator />
            <flux:menu.item icon="hand-thumb-up" wire:click="openConfirmation('sold', {{ $listing->id }})">{{ __('Mark as sold') }}</flux:menu.item>
            <flux:menu.item icon="archive-box" wire:click="openConfirmation('archive', {{ $listing->id }})">{{ __('Archive') }}</flux:menu.item>
        @elseif ($status === \App\Enums\ListingStatus::Sold)
            <flux:menu.item icon="arrow-path" wire:click="republish({{ $listing->id }})">{{ __('Publish again') }}</flux:menu.item>
            <flux:menu.separator />
            <flux:menu.item icon="archive-box" wire:click="openConfirmation('archive', {{ $listing->id }})">{{ __('Archive') }}</flux:menu.item>
        @elseif ($status === \App\Enums\ListingStatus::Archived)
            <flux:menu.item icon="arrow-path" wire:click="republish({{ $listing->id }})">{{ __('Publish again') }}</flux:menu.item>
        @elseif ($status === \App\Enums\ListingStatus::Suspended)
            <flux:menu.item icon="information-circle" wire:click="openConfirmation('reason', {{ $listing->id }})">{{ __('See reason') }}</flux:menu.item>
            @if (config('avytra.support.email'))
                <flux:menu.item icon="envelope" href="mailto:{{ config('avytra.support.email') }}">{{ __('Contact AVYTRA') }}</flux:menu.item>
            @endif
        @endif
    </flux:menu>
</flux:dropdown>
