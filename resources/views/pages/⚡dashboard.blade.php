<?php

use App\Models\Business;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public function rendering(View $view): void
    {
        $view->title(__('Panel'));
    }

    /**
     * @return Collection<int, Business>
     */
    #[Computed]
    public function businesses(): Collection
    {
        return Business::query()
            ->ownedBy(Auth::user())
            ->with(['category', 'location.province', 'location.municipality'])
            ->latest('updated_at')
            ->orderByDesc('id')
            ->limit(3)
            ->get();
    }
}; ?>

<div class="flex flex-col gap-8">
    <div class="flex flex-col gap-1">
        <flux:heading size="xl" level="1">{{ __('Hello, :name', ['name' => auth()->user()->name]) }}</flux:heading>
        <flux:text>{{ __('Panel') }}</flux:text>
    </div>

    {{-- Actionable notices and listings arrive in Phase 3; for now the panel shows the businesses. --}}
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
                        <div>
                            <flux:button size="sm" icon="pencil-square" :href="route('businesses.edit', $business)" wire:navigate>{{ __('Edit') }}</flux:button>
                        </div>
                    </flux:card>
                @endforeach
            </div>
        </section>
    @endif
</div>
