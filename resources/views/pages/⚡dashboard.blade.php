<?php

use App\Actions\Listings\ConfirmListingAvailability;
use App\Actions\Listings\ResumeListing;
use App\Enums\ListingStatus;
use App\Exceptions\InvalidListingTransition;
use App\Models\Business;
use App\Models\Listing;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Panel home (docs/09-dashboard-and-admin.md): what is published, whether it is up to
 * date and what needs doing. Not a metrics dashboard.
 */
new class extends Component {
    public function rendering(View $view): void
    {
        $view->title(__('Panel'));
    }

    /**
     * @return Collection<int, Listing>
     */
    #[Computed]
    public function listings(): Collection
    {
        return Listing::query()
            ->ownedBy(Auth::user())
            ->with(['business'])
            ->latest('updated_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Collection<int, Listing>
     */
    #[Computed]
    public function needingConfirmation(): Collection
    {
        return $this->listings->filter(fn (Listing $listing): bool => $listing->needsConfirmation())->values();
    }

    /**
     * @return Collection<int, Listing>
     */
    #[Computed]
    public function expired(): Collection
    {
        return $this->listings->where('status', ListingStatus::Expired)->values();
    }

    /**
     * @return Collection<int, Listing>
     */
    #[Computed]
    public function drafts(): Collection
    {
        return $this->listings->where('status', ListingStatus::Draft)->values();
    }

    #[Computed]
    public function unreadMessagesCount(): int
    {
        return $this->actor()->unreadContactRequestsCount();
    }

    /**
     * @return Collection<int, Business>
     */
    #[Computed]
    public function businesses(): Collection
    {
        return Business::query()
            ->ownedBy(Auth::user())
            ->with(['category', 'location.province', 'location.municipality', 'openListing'])
            ->latest('updated_at')
            ->orderByDesc('id')
            ->limit(3)
            ->get();
    }

    public function confirmAvailability(int $listingId, ConfirmListingAvailability $action): void
    {
        $listing = Listing::query()->with('business')->findOrFail($listingId);
        $this->authorize('confirm', $listing);

        $this->run(fn () => $action->handle($listing, $this->actor(), 'dashboard'), __('Thank you. Your listing stays available until :date.', [
            'date' => now()->addDays((int) config('avytra.freshness.confirmation_period_days'))->translatedFormat('j \d\e F'),
        ]));
    }

    public function resume(int $listingId, ResumeListing $action): void
    {
        $listing = Listing::query()->with('business')->findOrFail($listingId);
        $this->authorize('resume', $listing);

        $this->run(fn () => $action->handle($listing, $this->actor(), 'dashboard'), __('Listing published again.'));
    }

    private function run(callable $callback, string $successMessage): void
    {
        try {
            $callback();
        } catch (InvalidListingTransition $exception) {
            Flux::toast(variant: 'danger', text: $exception->userMessage());

            return;
        }

        unset($this->listings, $this->needingConfirmation, $this->expired, $this->drafts, $this->businesses);

        Flux::toast(variant: 'success', text: $successMessage);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}; ?>

<div class="flex flex-col gap-8">
    <div class="flex flex-col gap-1">
        <flux:heading size="xl" level="1">{{ __('Hello, :name', ['name' => auth()->user()->name]) }}</flux:heading>
        <flux:text>{{ __('Your businesses and listings, and what needs your attention.') }}</flux:text>
    </div>

    @if ($this->businesses->isEmpty() && $this->listings->isEmpty())
        <x-empty-state
            :heading="__('You do not have any businesses yet.')"
            :text="__('Publishing takes three steps: create the business with its basic data, complete the listing in a short guided process and confirm from time to time that it is still available.')"
        >
            <flux:button variant="primary" icon="plus" :href="route('panel.listings.create')" wire:navigate>
                {{ __('Publish my first business') }}
            </flux:button>
            <flux:button :href="route('businesses.create')" wire:navigate>
                {{ __('Create a business first') }}
            </flux:button>
        </x-empty-state>
    @else
        {{-- Actionable notices --}}
        @if ($this->unreadMessagesCount > 0 || $this->needingConfirmation->isNotEmpty() || $this->expired->isNotEmpty() || $this->drafts->isNotEmpty())
            <section class="flex flex-col gap-3">
                @if ($this->unreadMessagesCount > 0)
                    <flux:callout icon="envelope" variant="secondary">
                        <flux:callout.heading>{{ trans_choice('You have one unread message.|You have :count unread messages.', $this->unreadMessagesCount, ['count' => $this->unreadMessagesCount]) }}</flux:callout.heading>
                        <flux:callout.text>{{ __('Buyers are waiting for an answer. Reply from your email or from the messages page.') }}</flux:callout.text>
                        <x-slot name="actions">
                            <flux:button size="sm" variant="primary" icon="envelope-open" :href="route('panel.messages.index')" wire:navigate>{{ __('See messages') }}</flux:button>
                        </x-slot>
                    </flux:callout>
                @endif
                @foreach ($this->needingConfirmation as $listing)
                    <flux:callout icon="clock" variant="warning" wire:key="notice-confirm-{{ $listing->id }}">
                        <flux:callout.heading>{{ __('“:title” needs confirmation.', ['title' => $listing->title]) }}</flux:callout.heading>
                        <flux:callout.text>{{ __('Confirm that it is still available or it will be paused on :date.', ['date' => $listing->next_confirmation_at?->translatedFormat('j \d\e F')]) }}</flux:callout.text>
                        <x-slot name="actions">
                            <flux:button size="sm" variant="primary" icon="check-badge" wire:click="confirmAvailability({{ $listing->id }})">{{ __('Still available') }}</flux:button>
                        </x-slot>
                    </flux:callout>
                @endforeach

                @foreach ($this->expired as $listing)
                    <flux:callout icon="pause-circle" variant="danger" wire:key="notice-expired-{{ $listing->id }}">
                        <flux:callout.heading>{{ __('“:title” was paused for lack of confirmation.', ['title' => $listing->title]) }}</flux:callout.heading>
                        <flux:callout.text>{{ __('Your data is intact. Resume it with one click if the business is still available.') }}</flux:callout.text>
                        <x-slot name="actions">
                            <flux:button size="sm" variant="primary" icon="play" wire:click="resume({{ $listing->id }})">{{ __('Resume') }}</flux:button>
                        </x-slot>
                    </flux:callout>
                @endforeach

                @foreach ($this->drafts as $listing)
                    <flux:callout icon="document-text" variant="secondary" wire:key="notice-draft-{{ $listing->id }}">
                        <flux:callout.heading>{{ __('You have an unfinished draft for “:business”.', ['business' => $listing->business->name]) }}</flux:callout.heading>
                        <x-slot name="actions">
                            <flux:button size="sm" icon="arrow-right" :href="route('panel.listings.edit', $listing)" wire:navigate>{{ __('Continue') }}</flux:button>
                        </x-slot>
                    </flux:callout>
                @endforeach
            </section>
        @endif

        {{-- Listings --}}
        <section class="flex flex-col gap-4">
            <div class="flex items-center justify-between gap-4">
                <flux:heading size="lg" level="2">{{ __('My listings') }}</flux:heading>
                <flux:link :href="route('panel.listings.index')" wire:navigate class="text-sm">{{ __('See all') }}</flux:link>
            </div>

            @if ($this->listings->isEmpty())
                <x-empty-state
                    icon="document-text"
                    :heading="__('You do not have any listings yet.')"
                    :text="__('Publish one of your businesses in eight short steps.')"
                >
                    <flux:button variant="primary" icon="plus" :href="route('panel.listings.create')" wire:navigate>{{ __('New listing') }}</flux:button>
                </x-empty-state>
            @else
                <div class="flex flex-col divide-y divide-zinc-100 rounded-lg border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-700">
                    @foreach ($this->listings->take(5) as $listing)
                        <div class="flex items-center justify-between gap-3 px-4 py-3" wire:key="listing-{{ $listing->id }}">
                            <div class="flex min-w-0 flex-col">
                                <span class="truncate font-semibold text-ink dark:text-white">{{ $listing->title ?? __('Untitled draft') }}</span>
                                <span class="truncate text-sm text-slate">{{ $listing->business->name }}</span>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <x-listing-status-badge :listing="$listing" />
                                @if ($listing->status === ListingStatus::Draft)
                                    <flux:button size="sm" :href="route('panel.listings.edit', $listing)" wire:navigate>{{ __('Continue') }}</flux:button>
                                @elseif ($listing->status->isEditableByOwner())
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('panel.listings.edit', $listing)" wire:navigate :aria-label="__('Edit')" />
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Businesses --}}
        <section class="flex flex-col gap-4">
            <div class="flex items-center justify-between gap-4">
                <flux:heading size="lg" level="2">{{ __('My businesses') }}</flux:heading>
                <flux:link :href="route('businesses.index')" wire:navigate class="text-sm">{{ __('See all') }}</flux:link>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->businesses as $business)
                    <flux:card wire:key="business-{{ $business->id }}" class="flex flex-col gap-3">
                        <div class="flex items-start justify-between gap-3">
                            <flux:heading size="lg" class="truncate">{{ $business->name }}</flux:heading>
                            <flux:badge size="sm" :color="$business->business_type->badgeColor()">{{ $business->business_type->label() }}</flux:badge>
                        </div>
                        <flux:text class="truncate">
                            {{ $business->category->name }}
                            @if ($business->location) · {{ $business->location->municipality?->name ?? $business->location->province->name }} @else · {{ __('Online') }} @endif
                        </flux:text>
                        <div class="flex flex-wrap gap-2">
                            <flux:button size="sm" icon="pencil-square" :href="route('businesses.edit', $business)" wire:navigate>{{ __('Edit') }}</flux:button>
                            @if ($business->openListing === null)
                                <flux:button size="sm" variant="primary" icon="plus" :href="route('panel.listings.create', ['empresa' => $business->id])" wire:navigate>{{ __('New listing') }}</flux:button>
                            @endif
                        </div>
                    </flux:card>
                @endforeach
            </div>
        </section>
    @endif
</div>
