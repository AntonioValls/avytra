<?php

use App\Actions\Businesses\TransferBusinessOwnership;
use App\Enums\BusinessType;
use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'tipo', except: '')]
    public string $type = '';

    #[Url(as: 'sector', except: '')]
    public string $category = '';

    public ?int $transferBusinessId = null;

    public ?int $newOwnerId = null;

    public string $ownerSearch = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Business::class);
    }

    public function rendering(View $view): void
    {
        $view->title(__('Businesses'));
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Business>
     */
    #[Computed]
    public function businesses(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return Business::query()
            ->with(['owner', 'category', 'location.province', 'location.municipality'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhereHas('owner', function (Builder $query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when(BusinessType::tryFrom($this->type), fn (Builder $query, BusinessType $type) => $query->where('business_type', $type))
            ->when($this->category !== '', fn (Builder $query) => $query->where('category_id', (int) $this->category))
            ->latest('updated_at')
            ->orderByDesc('id')
            ->paginate(config('avytra.pagination.admin_rows'));
    }

    /**
     * @return Collection<int, Category>
     */
    #[Computed]
    public function sectors(): Collection
    {
        return Category::query()->roots()->active()->get();
    }

    /**
     * Candidates for the transfer modal: a short server-side search over name and email.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function ownerOptions(): Collection
    {
        $search = trim($this->ownerSearch);

        return User::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit(25)
            ->get();
    }

    public function openTransfer(int $businessId): void
    {
        $business = Business::query()->findOrFail($businessId);

        $this->authorize('transferOwnership', $business);

        $this->transferBusinessId = $business->id;
        $this->newOwnerId = null;
        $this->ownerSearch = '';
        $this->resetErrorBag();

        Flux::modal('transfer-ownership')->show();
    }

    public function transfer(TransferBusinessOwnership $transferOwnership): void
    {
        $business = Business::query()->findOrFail($this->transferBusinessId);

        $this->authorize('transferOwnership', $business);

        $this->validate([
            'newOwnerId' => ['required', 'integer', Rule::exists('users', 'id')],
        ], [], ['newOwnerId' => __('new owner')]);

        $transferOwnership->handle($business, User::query()->findOrFail($this->newOwnerId), Auth::user());

        unset($this->businesses);

        Flux::modal('transfer-ownership')->close();
        Flux::toast(variant: 'success', text: __('Owner changed.'));
    }

    #[Computed]
    public function transferBusiness(): ?Business
    {
        return $this->transferBusinessId === null ? null : Business::query()->with('owner')->find($this->transferBusinessId);
    }
}; ?>

<div class="flex flex-col gap-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex flex-col gap-1">
            <flux:heading size="xl" level="1">{{ __('Businesses') }}</flux:heading>
            <flux:text>{{ __('Every business on the platform, whoever owns it.') }}</flux:text>
        </div>

        <flux:button variant="primary" icon="plus" :href="route('admin.businesses.create')" wire:navigate>
            {{ __('New business') }}
        </flux:button>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search by name, owner or email')" clearable />

        <flux:select variant="listbox" wire:model.live="type" :placeholder="__('Any business type')">
            <flux:select.option value="">{{ __('Any business type') }}</flux:select.option>
            @foreach (BusinessType::cases() as $businessType)
                <flux:select.option :value="$businessType->value">{{ $businessType->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select variant="listbox" wire:model.live="category" :placeholder="__('Any sector')">
            <flux:select.option value="">{{ __('Any sector') }}</flux:select.option>
            @foreach ($this->sectors as $sector)
                <flux:select.option :value="$sector->id">{{ $sector->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if ($this->businesses->isEmpty())
        <x-empty-state
            icon="building-office-2"
            :heading="__('No businesses match.')"
            :text="__('Try another search or create a business on behalf of a user.')"
        />
    @else
        <flux:table :paginate="$this->businesses">
            <flux:table.columns>
                <flux:table.column>{{ __('Business') }}</flux:table.column>
                <flux:table.column>{{ __('Owner') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Sector') }}</flux:table.column>
                <flux:table.column>{{ __('Location') }}</flux:table.column>
                <flux:table.column>{{ __('Updated') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->businesses as $business)
                    <flux:table.row :key="$business->id">
                        <flux:table.cell variant="strong">{{ $business->name }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span>{{ $business->owner->name }}</span>
                                <span class="text-xs text-slate">{{ $business->owner->email }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$business->business_type->badgeColor()">{{ $business->business_type->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $business->category->name }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($business->location)
                                {{ $business->location->municipality?->name ?? '' }}@if ($business->location->municipality), @endif{{ $business->location->province->name }}
                            @else
                                {{ __('Online') }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $business->updated_at?->translatedFormat('j M Y') }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" :aria-label="__('Actions')" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" :href="route('admin.businesses.edit', $business)" wire:navigate>{{ __('Edit') }}</flux:menu.item>
                                    <flux:menu.item icon="arrow-right-circle" wire:click="openTransfer({{ $business->id }})">{{ __('Change owner') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="transfer-ownership" class="w-full max-w-lg">
        <form wire:submit="transfer" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ __('Change owner') }}</flux:heading>
                <flux:subheading>
                    {{ __('The business and its listings will belong to the new owner. The change is recorded in the audit log.') }}
                </flux:subheading>
            </div>

            @if ($this->transferBusiness)
                <flux:callout icon="building-storefront" variant="secondary">
                    <flux:callout.heading>{{ $this->transferBusiness->name }}</flux:callout.heading>
                    <flux:callout.text>{{ __('Current owner: :name', ['name' => $this->transferBusiness->owner->name]) }}</flux:callout.text>
                </flux:callout>
            @endif

            <flux:select
                wire:model="newOwnerId"
                variant="listbox"
                searchable
                :filter="false"
                :label="__('New owner')"
                :placeholder="__('Choose a user')"
            >
                <x-slot name="search">
                    <flux:select.search wire:model.live.debounce.300ms="ownerSearch" :placeholder="__('Search by name or email')" />
                </x-slot>

                @foreach ($this->ownerOptions as $user)
                    <flux:select.option :value="$user->id" wire:key="owner-{{ $user->id }}">{{ $user->name }} · {{ $user->email }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Change owner') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
