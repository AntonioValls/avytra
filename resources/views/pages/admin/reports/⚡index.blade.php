<?php

use App\Actions\Listings\ArchiveListing;
use App\Actions\Listings\PauseListing;
use App\Actions\Listings\SuspendListing;
use App\Actions\Reports\ResolveListingReport;
use App\Enums\ListingReportStatus;
use App\Exceptions\InvalidListingTransition;
use App\Models\ListingReport;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Reports inbox (docs/09): open reports first, resolve with an optional quick action on
 * the listing (pause, suspend, archive) or dismiss. Every decision is audited.
 */
new class extends Component {
    use WithPagination;

    #[Url(as: 'estado', except: 'open')]
    public string $status = 'open';

    public ?int $resolvingId = null;

    public string $quickAction = '';

    public string $notes = '';

    public function mount(): void
    {
        $this->authorize('viewAny', ListingReport::class);
    }

    public function rendering(View $view): void
    {
        $view->title(__('Reports'));
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, ListingReport>
     */
    #[Computed]
    public function reports(): LengthAwarePaginator
    {
        return ListingReport::query()
            ->with(['listing.business', 'reporter', 'resolvedBy'])
            ->when(ListingReportStatus::tryFrom($this->status), fn ($query, ListingReportStatus $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate(config('avytra.pagination.admin_rows'));
    }

    #[Computed]
    public function resolving(): ?ListingReport
    {
        return $this->resolvingId === null ? null : ListingReport::query()->with('listing')->find($this->resolvingId);
    }

    public function openResolve(int $reportId): void
    {
        $report = ListingReport::query()->findOrFail($reportId);
        $this->authorize('resolve', $report);

        $this->resolvingId = $report->id;
        $this->quickAction = '';
        $this->notes = '';

        Flux::modal('resolve-report')->show();
    }

    public function resolve(ResolveListingReport $resolver, PauseListing $pause, SuspendListing $suspend, ArchiveListing $archive): void
    {
        $report = ListingReport::query()->with('listing')->findOrFail((int) $this->resolvingId);
        $this->authorize('resolve', $report);

        $this->validate([
            'quickAction' => ['nullable', 'in:pause,suspend,archive'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $actor = $this->actor();
        $listing = $report->listing;
        $notes = trim($this->notes);

        try {
            match ($this->quickAction) {
                'pause' => $pause->handle($listing, $actor),
                'suspend' => $suspend->handle($listing, $actor, $notes !== '' ? $notes : $report->reason->label()),
                'archive' => $archive->handle($listing, $actor),
                default => null,
            };
        } catch (InvalidListingTransition $exception) {
            Flux::toast(variant: 'danger', text: $exception->userMessage());

            return;
        }

        $resolver->handle($report, $actor, ListingReportStatus::Resolved, $notes);

        $this->closeModal();
        Flux::toast(variant: 'success', text: __('Report resolved.'));
    }

    public function dismiss(int $reportId, ResolveListingReport $resolver): void
    {
        $report = ListingReport::query()->findOrFail($reportId);
        $this->authorize('resolve', $report);

        $resolver->handle($report, $this->actor(), ListingReportStatus::Dismissed);

        unset($this->reports);
        Flux::toast(text: __('Report dismissed.'));
    }

    private function closeModal(): void
    {
        $this->resolvingId = null;
        $this->quickAction = '';
        $this->notes = '';
        unset($this->reports, $this->resolving);

        Flux::modal('resolve-report')->close();
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
            <flux:heading size="xl" level="1">{{ __('Reports') }}</flux:heading>
            <flux:text>{{ __('What visitors flag on public listings. Resolve with an action or dismiss.') }}</flux:text>
        </div>

        <flux:radio.group wire:model.live="status" variant="segmented" size="sm">
            <flux:radio value="open">{{ __('Open') }}</flux:radio>
            <flux:radio value="resolved">{{ __('Resolved') }}</flux:radio>
            <flux:radio value="dismissed">{{ __('Dismissed') }}</flux:radio>
            <flux:radio value="">{{ __('All') }}</flux:radio>
        </flux:radio.group>
    </div>

    @if ($this->reports->isEmpty())
        <x-empty-state icon="flag" :heading="__('No reports here.')" :text="__('When a visitor reports a listing it will appear in this inbox.')" />
    @else
        <flux:table :paginate="$this->reports">
            <flux:table.columns>
                <flux:table.column>{{ __('Listing') }}</flux:table.column>
                <flux:table.column>{{ __('Reason') }}</flux:table.column>
                <flux:table.column>{{ __('Reporter') }}</flux:table.column>
                <flux:table.column>{{ __('Received') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->reports as $report)
                    <flux:table.row :key="$report->id">
                        <flux:table.cell variant="strong">
                            <div class="flex flex-col">
                                <flux:link :href="route('admin.listings.show', $report->listing)" wire:navigate>{{ $report->listing->title ?? __('Untitled draft') }}</flux:link>
                                <span class="text-xs text-slate">{{ $report->listing->business->name }} · <x-listing-status-badge :listing="$report->listing" size="sm" /></span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex max-w-xs flex-col">
                                <span>{{ $report->reason->label() }}</span>
                                @if ($report->message)
                                    <span class="truncate text-xs text-slate" title="{{ $report->message }}">{{ $report->message }}</span>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($report->reporter)
                                <div class="flex flex-col"><span>{{ $report->reporter->name }}</span><span class="text-xs text-slate">{{ $report->reporter->email }}</span></div>
                            @else
                                <span class="text-xs text-slate">{{ $report->reporter_email ?? __('Anonymous') }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $report->created_at?->translatedFormat('j M Y, H:i') }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-col gap-1">
                                <flux:badge size="sm" :color="$report->status->badgeColor()">{{ $report->status->label() }}</flux:badge>
                                @if ($report->resolution_notes)
                                    <span class="max-w-xs truncate text-xs text-slate" title="{{ $report->resolution_notes }}">{{ $report->resolution_notes }}</span>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($report->isOpen())
                                <div class="flex justify-end gap-1">
                                    @if ($report->listing->slug)
                                        <flux:button size="sm" variant="ghost" icon="arrow-top-right-on-square" :href="route('listings.show', $report->listing->slug)" target="_blank">{{ __('View') }}</flux:button>
                                    @endif
                                    <flux:button size="sm" variant="ghost" icon="check" wire:click="openResolve({{ $report->id }})">{{ __('Resolve') }}</flux:button>
                                    <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="dismiss({{ $report->id }})" wire:confirm="{{ __('Dismiss this report?') }}">{{ __('Dismiss') }}</flux:button>
                                </div>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="resolve-report" class="md:w-[28rem]">
        <form wire:submit="resolve" class="flex flex-col gap-6">
            <div class="flex flex-col gap-1">
                <flux:heading size="lg">{{ __('Resolve report') }}</flux:heading>
                @if ($this->resolving)
                    <flux:text>{{ $this->resolving->listing->title }} · {{ $this->resolving->reason->label() }}</flux:text>
                @endif
            </div>

            <flux:radio.group wire:model="quickAction" :label="__('Action on the listing')">
                <flux:radio value="" :label="__('None (already handled)')" />
                <flux:radio value="pause" :label="__('Pause')" />
                <flux:radio value="suspend" :label="__('Suspend (the owner is notified)')" />
                <flux:radio value="archive" :label="__('Archive')" />
            </flux:radio.group>

            <flux:textarea wire:model="notes" :label="__('Notes')" :description="__('Internal. Used as the suspension reason when you suspend.')" rows="3" maxlength="500" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check">{{ __('Resolve') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
