<?php

use App\Models\Business;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('viewAny', Business::class);
    }

    public function rendering(View $view): void
    {
        $view->title(__('My businesses'));
    }

    /**
     * Only the businesses the current user owns, never the ones they created for others.
     *
     * @return LengthAwarePaginator<int, Business>
     */
    #[Computed]
    public function businesses(): LengthAwarePaginator
    {
        return Business::query()
            ->ownedBy(Auth::user())
            ->with(['category', 'subcategory', 'location.province', 'location.municipality', 'openListing', 'media'])
            ->latest('updated_at')
            ->orderByDesc('id')
            ->paginate(config('avytra.pagination.panel_cards'));
    }
}; ?>

<div class="flex flex-col gap-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex flex-col gap-1">
            <flux:heading size="xl" level="1">{{ __('My businesses') }}</flux:heading>
            <flux:text>{{ __('The companies you own. Each one can be published when you decide.') }}</flux:text>
        </div>

        @if ($this->businesses->isNotEmpty())
            <flux:button variant="primary" icon="plus" :href="route('businesses.create')" wire:navigate>
                {{ __('New business') }}
            </flux:button>
        @endif
    </div>

    @if ($this->businesses->isEmpty())
        <x-empty-state
            :heading="__('You do not have any businesses yet.')"
            :text="__('Create your first business with its basic data. You will be able to publish it, pause it or sell it whenever you want.')"
        >
            <flux:button variant="primary" icon="plus" :href="route('businesses.create')" wire:navigate>
                {{ __('Create my first business') }}
            </flux:button>
        </x-empty-state>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->businesses as $business)
                <flux:card wire:key="business-{{ $business->id }}" class="flex flex-col gap-4">
                    @php $cover = $business->cover() ?? $business->galleryImages()->first(); @endphp
                    <div class="-mx-2 -mt-2 flex aspect-[16/7] items-center justify-center overflow-hidden rounded-md bg-mist dark:bg-zinc-800">
                        @if ($cover && $cover->hasGeneratedConversion('card'))
                            <img src="{{ $cover->getFullUrl('card') }}" alt="{{ $cover->getCustomProperty('alt', '') }}" width="{{ config('avytra.media.conversions.card.width') }}" height="{{ config('avytra.media.conversions.card.height') }}" loading="lazy" class="size-full object-cover" />
                        @else
                            <x-app-logo-icon class="size-10 text-zinc-300 dark:text-zinc-600" />
                        @endif
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 flex-col gap-1">
                            <flux:heading size="lg" class="truncate">{{ $business->name }}</flux:heading>
                            <flux:text class="truncate">
                                {{ $business->category->name }}@if ($business->subcategory) · {{ $business->subcategory->name }}@endif
                            </flux:text>
                        </div>
                        <flux:badge size="sm" :color="$business->business_type->badgeColor()" :icon="$business->business_type->icon()">
                            {{ $business->business_type->label() }}
                        </flux:badge>
                    </div>

                    <div class="flex flex-col gap-1 text-sm text-slate">
                        <div class="flex items-center gap-2">
                            <flux:icon.map-pin variant="micro" class="shrink-0" />
                            @if ($business->location)
                                <span class="truncate">{{ $business->location->municipality?->name }}@if ($business->location->municipality), @endif{{ $business->location->province->name }}</span>
                            @else
                                <span>{{ __('Online') }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:icon.clock variant="micro" class="shrink-0" />
                            <span>{{ __('Updated :date', ['date' => $business->updated_at?->translatedFormat('j M Y')]) }}</span>
                        </div>
                    </div>

                    <flux:separator />

                    <div class="flex flex-wrap items-center justify-between gap-2">
                        @if ($business->openListing)
                            <x-listing-status-badge :listing="$business->openListing" />
                        @else
                            <flux:text size="sm">{{ __('No listings yet') }}</flux:text>
                        @endif
                        <div class="flex gap-2">
                            <flux:button size="sm" icon="pencil-square" :href="route('businesses.edit', $business)" wire:navigate>
                                {{ __('Edit') }}
                            </flux:button>
                            @if ($business->openListing === null)
                                <flux:button size="sm" variant="primary" icon="plus" :href="route('panel.listings.create', ['empresa' => $business->id])" wire:navigate>
                                    {{ __('New listing') }}
                                </flux:button>
                            @elseif ($business->openListing->status === \App\Enums\ListingStatus::Draft)
                                <flux:button size="sm" variant="primary" icon="arrow-right" :href="route('panel.listings.edit', $business->openListing)" wire:navigate>
                                    {{ __('Continue') }}
                                </flux:button>
                            @else
                                <flux:button size="sm" variant="ghost" :href="route('panel.listings.index')" wire:navigate>
                                    {{ __('See listing') }}
                                </flux:button>
                            @endif
                        </div>
                    </div>
                </flux:card>
            @endforeach
        </div>

        <flux:pagination :paginator="$this->businesses" />
    @endif
</div>
