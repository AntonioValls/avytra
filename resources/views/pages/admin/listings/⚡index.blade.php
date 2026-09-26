<?php

use App\Enums\ListingStatus;
use App\Models\Listing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every listing on the platform with filters by status and condition. Actions live on
 * the detail page, where the timeline of events gives the context to decide.
 */
new class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'estado', except: '')]
    public string $status = '';

    #[Url(as: 'condicion', except: '')]
    public string $condition = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Listing::class);
    }

    public function rendering(View $view): void
    {
        $view->title(__('Listings'));
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedCondition(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Listing>
     */
    #[Computed]
    public function listings(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return Listing::query()
            ->with(['business.owner', 'operationTypes'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhereHas('business', function (Builder $query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhereHas('owner', function (Builder $query) use ($search): void {
                                    $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->when(ListingStatus::tryFrom($this->status), fn (Builder $query, ListingStatus $status) => $query->where('status', $status))
            ->when($this->condition === 'needs_confirmation', fn (Builder $query) => $query->needingConfirmation())
            ->when($this->condition === 'expired_recently', fn (Builder $query) => $query->expiredRecently())
            ->when($this->condition === 'failed_reminder', fn (Builder $query) => $query->withFailedReminder())
            ->when($this->condition === 'recent', fn (Builder $query) => $query->where('published_at', '>=', now()->subDay()))
            ->latest('updated_at')
            ->orderByDesc('id')
            ->paginate(config('avytra.pagination.admin_rows'));
    }
}; ?>

<div class="flex flex-col gap-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex flex-col gap-1">
            <flux:heading size="xl" level="1">{{ __('Listings') }}</flux:heading>
            <flux:text>{{ __('Every listing on the platform, whoever owns it.') }}</flux:text>
        </div>

        <flux:button variant="primary" icon="plus" :href="route('admin.listings.create')" wire:navigate>
            {{ __('New listing') }}
        </flux:button>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search by title, business, owner or email')" clearable />

        <flux:select variant="listbox" wire:model.live="status" :placeholder="__('Any status')">
            <flux:select.option value="">{{ __('Any status') }}</flux:select.option>
            @foreach (ListingStatus::cases() as $option)
                <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select variant="listbox" wire:model.live="condition" :placeholder="__('Any condition')">
            <flux:select.option value="">{{ __('Any condition') }}</flux:select.option>
            <flux:select.option value="needs_confirmation">{{ __('Needs confirmation') }}</flux:select.option>
            <flux:select.option value="expired_recently">{{ __('Paused automatically in the last :days days', ['days' => (int) config('avytra.freshness.expired_review_days')]) }}</flux:select.option>
            <flux:select.option value="failed_reminder">{{ __('Undelivered reminders') }}</flux:select.option>
            <flux:select.option value="recent">{{ __('Published in the last 24 hours') }}</flux:select.option>
        </flux:select>
    </div>

    @if ($this->listings->isEmpty())
        <x-empty-state icon="document-text" :heading="__('No listings match.')" :text="__('Try another search or filter.')" />
    @else
        <flux:table :paginate="$this->listings">
            <flux:table.columns>
                <flux:table.column>{{ __('Listing') }}</flux:table.column>
                <flux:table.column>{{ __('Owner') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Published') }}</flux:table.column>
                <flux:table.column>{{ __('Last confirmation') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->listings as $listing)
                    <flux:table.row :key="$listing->id">
                        <flux:table.cell variant="strong">
                            <div class="flex flex-col">
                                <flux:link :href="route('admin.listings.show', $listing)" wire:navigate>{{ $listing->title ?? __('Untitled draft') }}</flux:link>
                                <span class="text-xs text-slate">{{ $listing->business->name }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span>{{ $listing->business->owner->name }}</span>
                                <span class="text-xs text-slate">{{ $listing->business->owner->email }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell><x-listing-status-badge :listing="$listing" /></flux:table.cell>
                        <flux:table.cell>{{ $listing->published_at?->translatedFormat('j M Y') ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $listing->last_confirmed_at?->translatedFormat('j M Y') ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="sm" variant="ghost" icon="arrow-right" :href="route('admin.listings.show', $listing)" wire:navigate>{{ __('Manage') }}</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
