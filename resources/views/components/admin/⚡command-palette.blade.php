<?php

use App\Models\Business;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Global search of the admin area (docs/09): Ctrl/Cmd+K opens a command palette that looks
 * up users, businesses and listings server side and jumps to their admin pages.
 */
new class extends Component {
    private const LIMIT = 5;

    public string $query = '';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    /**
     * @return array{users: Collection<int, User>, businesses: Collection<int, Business>, listings: Collection<int, Listing>}
     */
    #[Computed]
    public function results(): array
    {
        $search = trim($this->query);

        if (mb_strlen($search) < 2) {
            return ['users' => new Collection, 'businesses' => new Collection, 'listings' => new Collection];
        }

        $like = "%{$search}%";

        return [
            'users' => User::query()
                ->where(fn (Builder $query) => $query->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like))
                ->orderBy('name')->limit(self::LIMIT)->get(),
            'businesses' => Business::query()
                ->with('owner')
                ->where('name', 'like', $like)
                ->orderBy('name')->limit(self::LIMIT)->get(),
            'listings' => Listing::query()
                ->with('business')
                ->where(fn (Builder $query) => $query->where('title', 'like', $like)->orWhere('slug', 'like', $like))
                ->latest('updated_at')->limit(self::LIMIT)->get(),
        ];
    }

    /**
     * Jumps to the admin page of the chosen result.
     */
    public function open(string $type, int $id): void
    {
        $this->authorize('viewAny', User::class);

        $this->query = '';

        match ($type) {
            'user' => $this->redirectRoute('admin.users.show', ['user' => $id], navigate: true),
            'business' => $this->redirectRoute('admin.businesses.edit', ['business' => $id], navigate: true),
            'listing' => $this->redirectRoute('admin.listings.show', ['listing' => $id], navigate: true),
            default => abort(404),
        };
    }
}; ?>

{{-- Alpine's "cmd" modifier only matches the meta key, so Ctrl+K is bound separately for Windows/Linux. --}}
<div
    x-data
    x-on:keydown.meta.k.document.prevent="$dispatch('modal-show', { name: 'admin-search' })"
    x-on:keydown.ctrl.k.document.prevent="$dispatch('modal-show', { name: 'admin-search' })"
>
    <flux:modal.trigger name="admin-search">
        <flux:sidebar.item icon="magnifying-glass" badge="Ctrl K">{{ __('Search') }}</flux:sidebar.item>
    </flux:modal.trigger>

    <flux:modal name="admin-search" variant="bare" class="my-[12vh] w-full max-w-[32rem] max-h-screen overflow-y-hidden">
        <flux:command :filter="false" class="inline-flex max-h-[76vh] flex-col border-none shadow-lg">
            <flux:command.input wire:model.live.debounce.250ms="query" :placeholder="__('Search users, businesses and listings…')" closable />

            <flux:command.items>
                @foreach ($this->results['users'] as $user)
                    <flux:command.item icon="user" wire:click="open('user', {{ $user->id }})" wire:key="cmd-user-{{ $user->id }}">
                        {{ $user->name }} <span class="ms-2 text-xs font-normal text-zinc-500">{{ $user->email }}</span>
                    </flux:command.item>
                @endforeach
                @foreach ($this->results['businesses'] as $business)
                    <flux:command.item icon="building-storefront" wire:click="open('business', {{ $business->id }})" wire:key="cmd-business-{{ $business->id }}">
                        {{ $business->name }} <span class="ms-2 text-xs font-normal text-zinc-500">{{ $business->owner->name }}</span>
                    </flux:command.item>
                @endforeach
                @foreach ($this->results['listings'] as $listing)
                    <flux:command.item icon="document-text" wire:click="open('listing', {{ $listing->id }})" wire:key="cmd-listing-{{ $listing->id }}">
                        {{ $listing->title ?? __('Untitled draft') }} <span class="ms-2 text-xs font-normal text-zinc-500">{{ $listing->business->name }}</span>
                    </flux:command.item>
                @endforeach
            </flux:command.items>
        </flux:command>
    </flux:modal>
</div>
