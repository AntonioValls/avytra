<?php

use App\Actions\Listings\ArchiveListing;
use App\Actions\Listings\ConfirmListingAvailability;
use App\Actions\Listings\CreateListingDraft;
use App\Actions\Listings\DeleteListingDraft;
use App\Actions\Listings\MarkListingAsSold;
use App\Actions\Listings\PauseListing;
use App\Actions\Listings\ResumeListing;
use App\Exceptions\BusinessAlreadyListed;
use App\Exceptions\InvalidListingTransition;
use App\Models\Listing;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * "My listings": every listing of the businesses the user owns, with the actions each
 * status allows. Destructive actions (archive, sold, delete) ask for confirmation in a modal.
 */
new class extends Component {
    use WithPagination;

    #[Locked]
    public ?int $pendingListingId = null;

    #[Locked]
    public string $pendingAction = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Listing::class);
    }

    public function rendering(View $view): void
    {
        $view->title(__('My listings'));
    }

    /**
     * @return LengthAwarePaginator<int, Listing>
     */
    #[Computed]
    public function listings(): LengthAwarePaginator
    {
        return Listing::query()
            ->ownedBy(Auth::user())
            ->with(['business', 'operationTypes'])
            ->latest('updated_at')
            ->orderByDesc('id')
            ->paginate(config('avytra.pagination.panel_cards'));
    }

    #[Computed]
    public function pendingListing(): ?Listing
    {
        return $this->pendingListingId === null ? null : Listing::query()->with('business')->find($this->pendingListingId);
    }

    public function confirmAvailability(int $listingId, ConfirmListingAvailability $action): void
    {
        $listing = $this->find($listingId);
        $this->authorize('confirm', $listing);

        $this->run(fn () => $action->handle($listing, $this->actor(), 'dashboard'), __('Thank you. Your listing stays available until :date.', [
            'date' => now()->addDays((int) config('avytra.freshness.confirmation_period_days'))->translatedFormat('j \d\e F'),
        ]));
    }

    public function pause(int $listingId, PauseListing $action): void
    {
        $listing = $this->find($listingId);
        $this->authorize('pause', $listing);

        $this->run(fn () => $action->handle($listing, $this->actor()), __('Listing paused.'));
    }

    public function resume(int $listingId, ResumeListing $action): void
    {
        $listing = $this->find($listingId);
        $this->authorize('resume', $listing);

        $this->run(fn () => $action->handle($listing, $this->actor(), 'dashboard'), __('Listing published again.'));
    }

    public function republish(int $listingId, CreateListingDraft $action): void
    {
        $listing = $this->find($listingId);
        $this->authorize('create', [Listing::class, $listing->business]);

        try {
            $draft = $action->handle($listing->business, $this->actor(), [], [], $listing);
        } catch (BusinessAlreadyListed $exception) {
            Flux::toast(variant: 'danger', text: $exception->userMessage());

            return;
        }

        $this->redirectRoute('listings.edit', ['listing' => $draft], navigate: true);
    }

    /**
     * Opens the confirmation modal for archive, sold or delete (or shows the suspension reason).
     */
    public function openConfirmation(string $action, int $listingId): void
    {
        $listing = $this->find($listingId);

        $this->authorize(match ($action) {
            'archive' => 'archive',
            'sold' => 'markSold',
            'delete' => 'delete',
            default => 'view',
        }, $listing);

        $this->pendingAction = $action;
        $this->pendingListingId = $listing->id;

        Flux::modal('confirm-listing-action')->show();
    }

    public function runPendingAction(ArchiveListing $archive, MarkListingAsSold $markSold, DeleteListingDraft $delete): void
    {
        $listing = $this->find((int) $this->pendingListingId);
        $action = $this->pendingAction;

        $this->pendingAction = '';
        $this->pendingListingId = null;

        Flux::modal('confirm-listing-action')->close();

        match ($action) {
            'archive' => $this->authorize('archive', $listing),
            'sold' => $this->authorize('markSold', $listing),
            'delete' => $this->authorize('delete', $listing),
            default => abort(404),
        };

        match ($action) {
            'archive' => $this->run(fn () => $archive->handle($listing, $this->actor()), __('Listing archived.')),
            'sold' => $this->run(fn () => $markSold->handle($listing, $this->actor()), __('Congratulations. The listing is marked as sold.')),
            'delete' => $this->run(fn () => $delete->handle($listing, $this->actor()), __('Draft deleted.')),
        };
    }

    private function run(callable $callback, string $successMessage): void
    {
        try {
            $callback();
        } catch (InvalidListingTransition $exception) {
            Flux::toast(variant: 'danger', text: $exception->userMessage());

            return;
        }

        unset($this->listings);

        Flux::toast(variant: 'success', text: $successMessage);
    }

    private function find(int $listingId): Listing
    {
        return Listing::query()->with('business')->findOrFail($listingId);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}; ?>

<div class="flex flex-col gap-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex flex-col gap-1">
            <flux:heading size="xl" level="1">{{ __('My listings') }}</flux:heading>
            <flux:text>{{ __('Everything you have published, paused or sold. Confirm availability regularly so buyers know your listing is up to date.') }}</flux:text>
        </div>

        @if ($this->listings->isNotEmpty())
            <flux:button variant="primary" icon="plus" :href="route('listings.create')" wire:navigate>
                {{ __('New listing') }}
            </flux:button>
        @endif
    </div>

    @if ($this->listings->isEmpty())
        <x-empty-state
            :heading="__('You do not have any listings yet.')"
            :text="__('When you publish your first business it will appear here. It takes eight short steps and you can stop at any time.')"
        >
            <flux:button variant="primary" icon="plus" :href="route('listings.create')" wire:navigate>
                {{ __('Publish a business') }}
            </flux:button>
        </x-empty-state>
    @else
        {{-- Table on wide screens --}}
        <div class="max-md:hidden">
            <flux:table :paginate="$this->listings">
                <flux:table.columns>
                    <flux:table.column>{{ __('Listing') }}</flux:table.column>
                    <flux:table.column>{{ __('Business') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Last confirmation') }}</flux:table.column>
                    <flux:table.column>{{ __('Next deadline') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->listings as $listing)
                        <flux:table.row :key="'row-'.$listing->id">
                            <flux:table.cell variant="strong">
                                <div class="flex flex-col gap-1">
                                    <span>{{ $listing->title ?? __('Untitled draft') }}</span>
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($listing->offeredOperationTypes() as $type)
                                            <flux:badge size="sm" :color="$type->badgeColor()" wire:key="row-{{ $listing->id }}-{{ $type->value }}">{{ $type->label() }}</flux:badge>
                                        @endforeach
                                    </div>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $listing->business->name }}</flux:table.cell>
                            <flux:table.cell><x-listing-status-badge :listing="$listing" /></flux:table.cell>
                            <flux:table.cell>{{ $listing->last_confirmed_at?->translatedFormat('j M Y') ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $listing->status === \App\Enums\ListingStatus::Published ? $listing->next_confirmation_at?->translatedFormat('j M Y') : '—' }}</flux:table.cell>
                            <flux:table.cell align="end">
                                @include('partials.listing-actions', ['listing' => $listing])
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>

        {{-- Cards on phones --}}
        <div class="flex flex-col gap-3 md:hidden">
            @foreach ($this->listings as $listing)
                <flux:card wire:key="card-{{ $listing->id }}" class="flex flex-col gap-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 flex-col gap-1">
                            <flux:heading class="truncate">{{ $listing->title ?? __('Untitled draft') }}</flux:heading>
                            <flux:text size="sm" class="truncate">{{ $listing->business->name }}</flux:text>
                        </div>
                        @include('partials.listing-actions', ['listing' => $listing])
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-listing-status-badge :listing="$listing" />
                        @if ($listing->last_confirmed_at)
                            <span class="text-xs text-slate">{{ __('Confirmed :date', ['date' => $listing->last_confirmed_at->translatedFormat('j M Y')]) }}</span>
                        @endif
                    </div>
                </flux:card>
            @endforeach

            <flux:pagination :paginator="$this->listings" />
        </div>
    @endif

    <flux:modal name="confirm-listing-action" class="w-full max-w-lg">
        @php $pending = $this->pendingListing; @endphp
        <div class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">
                    @switch($pendingAction)
                        @case('archive') {{ __('Archive this listing?') }} @break
                        @case('sold') {{ __('Mark this listing as sold?') }} @break
                        @case('delete') {{ __('Delete this draft?') }} @break
                        @case('reason') {{ __('Why was it suspended?') }} @break
                    @endswitch
                </flux:heading>
                <flux:subheading>
                    @switch($pendingAction)
                        @case('archive') {{ __('The listing is withdrawn but nothing is deleted. You can publish the business again whenever you want.') }} @break
                        @case('sold') {{ __('The listing will show “Sold” for a while and then disappear from the marketplace. This cannot be undone.') }} @break
                        @case('delete') {{ __('The draft and everything you wrote in it will be deleted. The business itself stays.') }} @break
                        @case('reason') {{ $pending?->suspension_reason ?? __('No reason was recorded.') }} @break
                    @endswitch
                </flux:subheading>
            </div>

            @if ($pending && $pendingAction !== 'reason')
                <flux:callout icon="document-text" variant="secondary">
                    <flux:callout.heading>{{ $pending->title ?? __('Untitled draft') }}</flux:callout.heading>
                    <flux:callout.text>{{ $pending->business->name }}</flux:callout.text>
                </flux:callout>
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ $pendingAction === 'reason' ? __('Close') : __('Cancel') }}</flux:button>
                </flux:modal.close>
                @if ($pendingAction !== 'reason' && $pendingAction !== '')
                    <flux:button :variant="$pendingAction === 'delete' ? 'danger' : 'primary'" wire:click="runPendingAction">
                        @switch($pendingAction)
                            @case('archive') {{ __('Archive') }} @break
                            @case('sold') {{ __('Mark as sold') }} @break
                            @case('delete') {{ __('Delete draft') }} @break
                        @endswitch
                    </flux:button>
                @endif
            </div>
        </div>
    </flux:modal>
</div>
