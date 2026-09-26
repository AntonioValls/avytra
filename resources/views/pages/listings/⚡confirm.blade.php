<?php

use App\Actions\Listings\ConfirmListingAvailability;
use App\Actions\Listings\MarkListingAsSold;
use App\Actions\Listings\PauseListing;
use App\Actions\Listings\ResumeListing;
use App\Enums\ListingStatus;
use App\Exceptions\InvalidListingTransition;
use App\Models\Listing;
use App\Models\User;
use App\Support\Listings\ConfirmationLink;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Is it still available?": the page reminder emails link to (ADR-007). Signed in, owner
 * only, one button. The temporary signature of the link is checked once in mount and kept
 * locked; an expired link shows the same page without the action and points to the panel.
 */
new class extends Component {
    #[Locked]
    public int $listingId;

    #[Locked]
    public bool $linkIsValid = false;

    #[Locked]
    public bool $confirmed = false;

    public function mount(Listing $listing): void
    {
        $this->authorize('view', $listing);

        $this->listingId = $listing->id;
        $this->linkIsValid = ConfirmationLink::isValid($listing, request());
    }

    public function rendering(View $view): void
    {
        $view->title(__('Is it still available?'));
    }

    #[Computed]
    public function listing(): Listing
    {
        return Listing::query()->with(['business.category'])->findOrFail($this->listingId);
    }

    /**
     * Published → confirm; paused or expired → resume. Both restart the freshness clock.
     */
    public function confirm(ConfirmListingAvailability $confirm, ResumeListing $resume): void
    {
        abort_unless($this->linkIsValid, 403);

        $listing = $this->fresh();

        if ($listing->status === ListingStatus::Published) {
            $this->authorize('confirm', $listing);
            $this->run(fn () => $confirm->handle($listing, $this->actor(), 'email_link'));

            return;
        }

        $this->authorize('resume', $listing);
        $this->run(fn () => $resume->handle($listing, $this->actor(), 'email_link'));
    }

    public function pause(PauseListing $action): void
    {
        $listing = $this->fresh();
        $this->authorize('pause', $listing);

        try {
            $action->handle($listing, $this->actor());
        } catch (InvalidListingTransition $exception) {
            Flux::toast(variant: 'danger', text: $exception->userMessage());

            return;
        }

        $this->redirectRoute('panel.listings.index', navigate: true);
    }

    public function markSold(MarkListingAsSold $action): void
    {
        $listing = $this->fresh();
        $this->authorize('markSold', $listing);

        Flux::modal('confirm-sold')->close();

        try {
            $action->handle($listing, $this->actor());
        } catch (InvalidListingTransition $exception) {
            Flux::toast(variant: 'danger', text: $exception->userMessage());

            return;
        }

        $this->redirectRoute('panel.listings.index', navigate: true);
    }

    private function run(callable $callback): void
    {
        try {
            $callback();
        } catch (InvalidListingTransition $exception) {
            Flux::toast(variant: 'danger', text: $exception->userMessage());

            return;
        }

        unset($this->listing);
        $this->confirmed = true;
    }

    private function fresh(): Listing
    {
        return Listing::query()->with('business')->findOrFail($this->listingId);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}; ?>

@php
    $listing = $this->listing;
    $status = $listing->status;
    $canAct = $this->linkIsValid && ! $this->confirmed && in_array($status, [ListingStatus::Published, ListingStatus::Paused, ListingStatus::Expired], true);
@endphp

<div class="mx-auto flex w-full max-w-2xl flex-col gap-8">
    <div class="flex flex-col gap-1">
        <flux:heading size="xl" level="1">
            @if ($status === ListingStatus::Paused)
                {{ __('Do you want to reactivate it?') }}
            @else
                {{ __('Is it still available?') }}
            @endif
        </flux:heading>
        <flux:text>{{ __('Buyers only see listings whose availability was confirmed recently.') }}</flux:text>
    </div>

    <flux:card class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center gap-2">
            <x-listing-status-badge :listing="$listing" />
            @foreach ($listing->offeredOperationTypes() as $type)
                <flux:badge size="sm" :color="$type->badgeColor()" wire:key="op-{{ $type->value }}">{{ $type->label() }}</flux:badge>
            @endforeach
        </div>
        <flux:heading size="lg" level="2">{{ $listing->title ?? __('Untitled draft') }}</flux:heading>
        <flux:text>{{ $listing->business->name }} · {{ $listing->business->category->name }}</flux:text>
        <dl class="grid gap-2 text-sm sm:grid-cols-2">
            <div><dt class="text-slate">{{ __('Last confirmation') }}</dt><dd class="font-semibold text-ink dark:text-white">{{ $listing->last_confirmed_at?->translatedFormat('j \d\e F \d\e Y') ?? '—' }}</dd></div>
            <div><dt class="text-slate">{{ __('Next deadline') }}</dt><dd class="font-semibold text-ink dark:text-white">{{ $status === ListingStatus::Published ? $listing->next_confirmation_at?->translatedFormat('j \d\e F \d\e Y') : '—' }}</dd></div>
        </dl>
    </flux:card>

    @if ($this->confirmed)
        <flux:callout icon="check-badge" variant="success">
            <flux:callout.heading>{{ __('Thank you. Your listing stays available until :date.', ['date' => $listing->next_confirmation_at?->translatedFormat('j \d\e F')]) }}</flux:callout.heading>
            <flux:callout.text>{{ __('We will remind you by email before that date. You can also confirm at any time from your panel.') }}</flux:callout.text>
            <x-slot name="actions">
                @if ($listing->slug)
                    <flux:button size="sm" icon="eye" :href="route('listings.show', $listing->slug)" wire:navigate>{{ __('See the public page') }}</flux:button>
                @endif
                <flux:button size="sm" variant="ghost" :href="route('panel.listings.index')" wire:navigate>{{ __('Go to my listings') }}</flux:button>
            </x-slot>
        </flux:callout>
    @elseif (! $this->linkIsValid)
        <flux:callout icon="clock" variant="warning">
            <flux:callout.heading>{{ __('This link has expired.') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Confirmation links from our emails work for :days days. Nothing has changed: you can still confirm, pause or reactivate the listing from your panel.', ['days' => (int) config('avytra.freshness.confirmation_link_ttl_days')]) }}</flux:callout.text>
            <x-slot name="actions">
                <flux:button size="sm" variant="primary" icon="arrow-right" :href="route('panel.listings.index')" wire:navigate>{{ __('Go to my listings') }}</flux:button>
            </x-slot>
        </flux:callout>
    @elseif ($status === ListingStatus::Published)
        <div class="flex flex-col gap-4">
            <flux:text>{{ __('If the business is still for sale, confirm it with one click. If we do not hear from you it will be paused on :date; nothing is deleted.', ['date' => $listing->next_confirmation_at?->translatedFormat('j \d\e F')]) }}</flux:text>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <flux:button variant="primary" icon="check-badge" wire:click="confirm">{{ __('Yes, it is still available') }}</flux:button>
                <flux:modal.trigger name="confirm-sold">
                    <flux:button variant="ghost" icon="hand-thumb-up">{{ __('Mark as sold') }}</flux:button>
                </flux:modal.trigger>
                <flux:button variant="ghost" icon="pause" wire:click="pause">{{ __('Pause') }}</flux:button>
            </div>
        </div>
    @elseif ($status === ListingStatus::Expired)
        <div class="flex flex-col gap-4">
            <flux:callout icon="pause-circle" variant="warning">
                <flux:callout.heading>{{ __('Paused on :date for lack of confirmation.', ['date' => $listing->expired_at?->translatedFormat('j \d\e F')]) }}</flux:callout.heading>
                <flux:callout.text>{{ __('Your data is intact. Resume it with one click if the business is still available.') }}</flux:callout.text>
            </flux:callout>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <flux:button variant="primary" icon="play" wire:click="confirm">{{ __('Yes, it is still available') }}</flux:button>
                <flux:modal.trigger name="confirm-sold">
                    <flux:button variant="ghost" icon="hand-thumb-up">{{ __('Mark as sold') }}</flux:button>
                </flux:modal.trigger>
            </div>
        </div>
    @elseif ($status === ListingStatus::Paused)
        <div class="flex flex-col gap-4">
            <flux:callout icon="pause" variant="secondary">
                <flux:callout.heading>{{ __('It is paused by you.') }}</flux:callout.heading>
                <flux:callout.text>{{ __('Nobody can see it until you reactivate it. Reactivating counts as confirming that it is available.') }}</flux:callout.text>
            </flux:callout>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <flux:button variant="primary" icon="play" wire:click="confirm">{{ __('Reactivate') }}</flux:button>
                <flux:modal.trigger name="confirm-sold">
                    <flux:button variant="ghost" icon="hand-thumb-up">{{ __('Mark as sold') }}</flux:button>
                </flux:modal.trigger>
            </div>
        </div>
    @else
        <flux:callout icon="information-circle" variant="secondary">
            <flux:callout.heading>{{ $status->label() }}</flux:callout.heading>
            <flux:callout.text>{{ $status->description() }}</flux:callout.text>
            <x-slot name="actions">
                <flux:button size="sm" icon="arrow-right" :href="route('panel.listings.index')" wire:navigate>{{ __('Go to my listings') }}</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    @if ($canAct)
        <flux:modal name="confirm-sold" class="w-full max-w-lg">
            <div class="flex flex-col gap-6">
                <div>
                    <flux:heading size="lg">{{ __('Mark this listing as sold?') }}</flux:heading>
                    <flux:subheading>{{ __('The listing will show “Sold” for a while and then disappear from the marketplace. This cannot be undone.') }}</flux:subheading>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" wire:click="markSold">{{ __('Mark as sold') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
