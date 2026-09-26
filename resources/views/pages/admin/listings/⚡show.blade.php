<?php

use App\Actions\Listings\ArchiveListing;
use App\Actions\Listings\ChangeListingSlug;
use App\Actions\Listings\ConfirmListingAvailability;
use App\Actions\Listings\MarkListingAsSold;
use App\Actions\Listings\PauseListing;
use App\Actions\Listings\PublishListing;
use App\Actions\Listings\ResendFailedReminder;
use App\Actions\Listings\ResumeListing;
use App\Actions\Listings\SuspendListing;
use App\Actions\Listings\UnsuspendListing;
use App\Enums\ListingStatus;
use App\Exceptions\InvalidListingTransition;
use App\Exceptions\ListingNotPublishable;
use App\Models\Listing;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Detail of a listing for the superadmin: data, timeline of events and every transition,
 * including suspension with a reason and confirmation on behalf of the owner.
 * Everything goes through the Actions, so it is recorded in listing_events and audit_logs.
 */
new class extends Component {
    #[Locked]
    public int $listingId;

    public string $suspensionReason = '';

    public string $newSlug = '';

    /** @var list<string> */
    public array $publishErrors = [];

    public function mount(Listing $listing): void
    {
        $this->authorize('view', $listing);

        $this->listingId = $listing->id;
        $this->newSlug = $listing->slug ?? '';
    }

    public function rendering(View $view): void
    {
        $view->title($this->listing->title ?? __('Untitled draft'));
    }

    #[Computed]
    public function listing(): Listing
    {
        return Listing::query()
            ->with(['business.owner', 'business.category', 'business.location.province', 'business.location.municipality', 'operationTypes', 'financialMetrics', 'events.actor', 'events.onBehalfOf', 'slugRedirects', 'contactRequests'])
            ->findOrFail($this->listingId);
    }

    public function publish(PublishListing $action): void
    {
        $listing = $this->fresh();
        $this->authorize('publish', $listing);

        try {
            $action->handle($listing, $this->actor());
        } catch (ListingNotPublishable $exception) {
            $this->publishErrors = $exception->report->messages();

            return;
        } catch (InvalidListingTransition $exception) {
            Flux::toast(variant: 'danger', text: $exception->userMessage());

            return;
        }

        $this->done(__('Listing published.'));
    }

    public function pause(PauseListing $action): void
    {
        $listing = $this->fresh();
        $this->authorize('pause', $listing);

        $this->run(fn () => $action->handle($listing, $this->actor()), __('Listing paused.'));
    }

    public function resume(ResumeListing $action): void
    {
        $listing = $this->fresh();
        $this->authorize('resume', $listing);

        $this->run(fn () => $action->handle($listing, $this->actor(), 'admin'), __('Listing published again.'));
    }

    public function confirmOnBehalf(ConfirmListingAvailability $action): void
    {
        $listing = $this->fresh();
        $this->authorize('confirm', $listing);

        $this->run(fn () => $action->handle($listing, $this->actor(), 'admin'), __('Availability confirmed on behalf of the owner.'));
    }

    /**
     * Resends the reminder or pause email that could not be delivered (docs/13).
     */
    public function resendReminder(ResendFailedReminder $action): void
    {
        $listing = $this->fresh()->load('events');
        $this->authorize('confirm', $listing);

        $this->run(fn () => $action->handle($listing, $this->actor()), __('Email sent again to the owner.'));
    }

    public function markSold(MarkListingAsSold $action): void
    {
        $listing = $this->fresh();
        $this->authorize('markSold', $listing);

        $this->run(fn () => $action->handle($listing, $this->actor()), __('Listing marked as sold.'));
    }

    public function archive(ArchiveListing $action): void
    {
        $listing = $this->fresh();
        $this->authorize('archive', $listing);

        $this->run(fn () => $action->handle($listing, $this->actor()), __('Listing archived.'));
    }

    public function suspend(SuspendListing $action): void
    {
        $listing = $this->fresh();
        $this->authorize('suspend', $listing);

        $this->validate(['suspensionReason' => ['required', 'string', 'min:5', 'max:500']], [], ['suspensionReason' => __('reason')]);

        $reason = trim($this->suspensionReason);
        $this->suspensionReason = '';

        Flux::modal('suspend-listing')->close();

        $this->run(fn () => $action->handle($listing, $this->actor(), $reason), __('Listing suspended. The owner has been notified.'));
    }

    public function unsuspend(UnsuspendListing $action): void
    {
        $listing = $this->fresh();
        $this->authorize('unsuspend', $listing);

        $this->run(fn () => $action->handle($listing, $this->actor()), __('Suspension lifted.'));
    }

    public function changeSlug(ChangeListingSlug $action): void
    {
        $listing = $this->fresh();
        $this->authorize('changeSlug', $listing);

        $this->validate(['newSlug' => ['required', 'string', 'max:140']], [], ['newSlug' => __('URL')]);

        try {
            $action->handle($listing, $this->actor(), $this->newSlug);
        } catch (\InvalidArgumentException) {
            $this->addError('newSlug', __('That URL is not valid or is already in use.'));

            return;
        }

        $this->done(__('URL changed. The previous one redirects to the new one.'));
    }

    private function run(callable $callback, string $successMessage): void
    {
        try {
            $callback();
        } catch (InvalidListingTransition $exception) {
            Flux::toast(variant: 'danger', text: $exception->userMessage());

            return;
        }

        $this->done($successMessage);
    }

    private function done(string $message): void
    {
        $this->publishErrors = [];
        unset($this->listing);
        $this->newSlug = $this->listing->slug ?? '';

        Flux::toast(variant: 'success', text: $message);
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
@endphp

<div class="flex flex-col gap-8">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('admin.listings.index')" wire:navigate>{{ __('Listings') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $listing->title ?? __('Untitled draft') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="flex flex-col gap-2">
            <div class="flex flex-wrap items-center gap-2">
                <x-listing-status-badge :listing="$listing" />
                @foreach ($listing->offeredOperationTypes() as $type)
                    <flux:badge size="sm" :color="$type->badgeColor()" wire:key="op-{{ $type->value }}">{{ $type->label() }}</flux:badge>
                @endforeach
            </div>
            <flux:heading size="xl" level="1">{{ $listing->title ?? __('Untitled draft') }}</flux:heading>
            <flux:text>
                {{ $listing->business->name }} · {{ $listing->business->category->name }}
                @if ($listing->business->location) · {{ $listing->business->location->municipality?->name ?? $listing->business->location->province->name }} @endif
            </flux:text>
            <flux:text size="sm" class="text-slate">
                {{ __('Owner: :name (:email)', ['name' => $listing->business->owner->name, 'email' => $listing->business->owner->email]) }}
            </flux:text>
        </div>

        <div class="flex flex-wrap gap-2">
            @if ($status->isEditableByOwner() || $status === ListingStatus::Suspended)
                <flux:button icon="pencil-square" :href="route('admin.listings.edit', $listing)" wire:navigate>{{ __('Edit') }}</flux:button>
            @endif
            @if ($status === ListingStatus::Draft)
                <flux:button variant="primary" icon="rocket-launch" wire:click="publish">{{ __('Publish') }}</flux:button>
            @endif
            @if ($status === ListingStatus::Published)
                <flux:button variant="primary" icon="check-badge" wire:click="confirmOnBehalf">{{ __('Confirm on behalf of the owner') }}</flux:button>
                <flux:button icon="pause" wire:click="pause">{{ __('Pause') }}</flux:button>
            @endif
            @if ($status === ListingStatus::Paused || $status === ListingStatus::Expired)
                <flux:button variant="primary" icon="play" wire:click="resume">{{ __('Resume') }}</flux:button>
            @endif
            @if (in_array($status, [ListingStatus::Published, ListingStatus::Paused, ListingStatus::Expired], true))
                <flux:button icon="hand-thumb-up" wire:click="markSold">{{ __('Mark as sold') }}</flux:button>
                <flux:modal.trigger name="suspend-listing">
                    <flux:button variant="danger" icon="no-symbol">{{ __('Suspend') }}</flux:button>
                </flux:modal.trigger>
            @endif
            @if ($status === ListingStatus::Suspended)
                <flux:button variant="primary" icon="lock-open" wire:click="unsuspend">{{ __('Lift suspension') }}</flux:button>
            @endif
            @if ($status !== ListingStatus::Archived)
                <flux:button icon="archive-box" wire:click="archive">{{ __('Archive') }}</flux:button>
            @endif
        </div>
    </div>

    @if ($publishErrors !== [])
        <flux:callout icon="exclamation-triangle" variant="danger">
            <flux:callout.heading>{{ __('The listing cannot be published yet.') }}</flux:callout.heading>
            <flux:callout.text>
                <ul class="list-disc ps-4">
                    @foreach ($publishErrors as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </flux:callout.text>
        </flux:callout>
    @endif

    @if ($failedReminder = $listing->latestFailedReminder())
        <flux:callout icon="bell-alert" variant="danger">
            <flux:callout.heading>{{ __('The email of :date could not be delivered.', ['date' => $failedReminder->created_at?->translatedFormat('j M Y, H:i')]) }}</flux:callout.heading>
            <flux:callout.text>{{ __('Nothing is retried automatically. Check the owner\'s address (:email) and send it again, or confirm on their behalf.', ['email' => $listing->business->owner->email]) }}</flux:callout.text>
            <x-slot name="actions">
                <flux:button size="sm" variant="primary" icon="paper-airplane" wire:click="resendReminder">{{ __('Resend') }}</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    @if ($status === ListingStatus::Suspended)
        <flux:callout icon="no-symbol" variant="danger">
            <flux:callout.heading>{{ __('Suspended on :date', ['date' => $listing->suspended_at?->translatedFormat('j M Y, H:i')]) }}</flux:callout.heading>
            <flux:callout.text>{{ $listing->suspension_reason }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid gap-8 lg:grid-cols-[2fr_1fr]">
        <div class="flex flex-col gap-8">
            <flux:card class="flex flex-col gap-4">
                <flux:heading size="lg" level="2">{{ __('Summary') }}</flux:heading>
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-slate">{{ __('Price') }}</dt><dd><x-price :text="\App\Support\Listings\PriceFormatter::forListing($listing)" :negotiable="$listing->is_price_negotiable" size="sm" /></dd></div>
                    <div><dt class="text-slate">{{ __('Public URL') }}</dt><dd class="font-mono text-xs">{{ $listing->slug ?? '—' }}</dd></div>
                    <div><dt class="text-slate">{{ __('Published') }}</dt><dd>{{ $listing->published_at?->translatedFormat('j M Y, H:i') ?? '—' }}</dd></div>
                    <div><dt class="text-slate">{{ __('Last confirmation') }}</dt><dd>{{ $listing->last_confirmed_at?->translatedFormat('j M Y, H:i') ?? '—' }}</dd></div>
                    <div><dt class="text-slate">{{ __('Next deadline') }}</dt><dd>{{ $listing->next_confirmation_at?->translatedFormat('j M Y') ?? '—' }}</dd></div>
                    <div><dt class="text-slate">{{ __('Preferred contact method') }}</dt><dd>{{ $listing->preferred_contact_method?->label() ?? '—' }} @if ($listing->contact_name) · {{ $listing->contact_name }} @endif</dd></div>
                    <div><dt class="text-slate">{{ __('Created by') }}</dt><dd>{{ $listing->creator?->name ?? '—' }}</dd></div>
                    <div><dt class="text-slate">{{ __('Updated') }}</dt><dd>{{ $listing->updated_at?->translatedFormat('j M Y, H:i') }}</dd></div>
                </dl>
            </flux:card>

            <flux:card class="flex flex-col gap-4">
                <flux:heading size="lg" level="2">{{ __('History') }}</flux:heading>

                @if ($listing->events->isEmpty())
                    <flux:text>{{ __('No events yet.') }}</flux:text>
                @else
                    <flux:timeline>
                        @foreach ($listing->events as $event)
                            <flux:timeline.item wire:key="event-{{ $event->id }}">
                                <flux:timeline.indicator :color="$event->type->color()">
                                    <flux:icon :icon="$event->type->icon()" variant="micro" />
                                </flux:timeline.indicator>
                                <flux:timeline.content>
                                    <flux:heading size="sm">{{ $event->type->label() }}</flux:heading>
                                    <flux:text size="sm">
                                        {{ $event->created_at?->translatedFormat('j M Y, H:i') }}
                                        · {{ $event->actor?->name ?? __('System') }}
                                        @if ($event->onBehalfOf) · {{ __('on behalf of :name', ['name' => $event->onBehalfOf->name]) }} @endif
                                        @if (isset($event->payload['reason'])) · {{ $event->payload['reason'] }} @endif
                                        @if (isset($event->payload['channel'])) · {{ $event->payload['channel'] }} @endif
                                    </flux:text>
                                </flux:timeline.content>
                            </flux:timeline.item>
                        @endforeach
                    </flux:timeline>
                @endif
            </flux:card>
        </div>

        <div class="flex flex-col gap-8">
            @if ($listing->hasBeenPublished())
                <flux:card class="flex flex-col gap-4">
                    <flux:heading size="lg" level="2">{{ __('Public URL') }}</flux:heading>
                    <flux:text size="sm">{{ __('Changing it keeps the old address as a permanent redirect.') }}</flux:text>
                    <form wire:submit="changeSlug" class="flex flex-col gap-3">
                        <flux:input wire:model="newSlug" :label="__('Slug')" maxlength="140" />
                        <div>
                            <flux:button type="submit" size="sm">{{ __('Change URL') }}</flux:button>
                        </div>
                    </form>
                    @if ($listing->slugRedirects->isNotEmpty())
                        <flux:text size="sm" class="text-slate">{{ __('Previous URLs: :slugs', ['slugs' => $listing->slugRedirects->pluck('old_slug')->join(', ')]) }}</flux:text>
                    @endif
                </flux:card>
            @endif

            {{-- Relayed messages (ADR-019): read-only for the superadmin; the owner answers from their panel. --}}
            <flux:card class="flex flex-col gap-4">
                <flux:heading size="lg" level="2">{{ __('Messages received') }} <span class="font-normal text-slate">· {{ $listing->contactRequests->count() }}</span></flux:heading>
                @if ($listing->contactRequests->isEmpty())
                    <flux:text size="sm">{{ __('Nobody has written from this listing yet.') }}</flux:text>
                @else
                    <ul class="flex flex-col divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($listing->contactRequests->sortByDesc('id') as $request)
                            <li class="flex flex-col gap-1 py-2 text-sm" wire:key="request-{{ $request->id }}">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="truncate font-semibold">{{ $request->sender_name }}</span>
                                    <span class="shrink-0 text-xs text-slate">{{ $request->created_at?->translatedFormat('j M Y, H:i') }}</span>
                                </div>
                                <span class="line-clamp-2 text-slate">{{ $request->message }}</span>
                                <div class="flex flex-wrap gap-1">
                                    <flux:badge size="sm" :color="$request->isRead() ? 'zinc' : 'lime'">{{ $request->isRead() ? __('Read') : __('Unread') }}</flux:badge>
                                    @unless ($request->wasDelivered())
                                        <flux:badge size="sm" color="amber" icon="exclamation-triangle">{{ __('Not delivered: :error', ['error' => $request->delivery_error]) }}</flux:badge>
                                    @endunless
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </flux:card>
        </div>
    </div>

    <flux:modal name="suspend-listing" class="w-full max-w-lg">
        <form wire:submit="suspend" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ __('Suspend this listing?') }}</flux:heading>
                <flux:subheading>{{ __('It disappears from the marketplace and the owner cannot edit it. They receive an email with the reason.') }}</flux:subheading>
            </div>

            <flux:textarea wire:model="suspensionReason" :label="__('Reason')" rows="3" maxlength="500" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" type="submit">{{ __('Suspend') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
