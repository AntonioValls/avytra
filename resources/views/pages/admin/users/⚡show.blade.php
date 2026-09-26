<?php

use App\Actions\Users\SendSetPasswordLink;
use App\Actions\Users\UpdateUserByAdmin;
use App\Livewire\Forms\AdminUserForm;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Listing;
use App\Models\User;
use App\Support\Audit\AuditActions;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * One account for the superadmin: data (editable, audited), the businesses and listings it
 * owns with shortcuts to create more on its behalf, the set-password link and its audit trail.
 */
new class extends Component {
    #[Locked]
    public int $userId;

    public AdminUserForm $form;

    public function mount(User $user): void
    {
        $this->authorize('view', $user);

        $this->userId = $user->id;
        $this->form->fillFromUser($user);
    }

    public function rendering(View $view): void
    {
        $view->title($this->user->name);
    }

    #[Computed]
    public function user(): User
    {
        return User::query()->findOrFail($this->userId);
    }

    /**
     * @return Collection<int, Business>
     */
    #[Computed]
    public function businesses(): Collection
    {
        return Business::query()
            ->where('owner_user_id', $this->userId)
            ->with(['category', 'openListing'])
            ->latest('updated_at')
            ->get();
    }

    /**
     * @return Collection<int, Listing>
     */
    #[Computed]
    public function listings(): Collection
    {
        return Listing::query()
            ->ownedBy($this->user)
            ->with('business')
            ->latest('updated_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Collection<int, AuditLog>
     */
    #[Computed]
    public function auditEntries(): Collection
    {
        return AuditLog::query()
            ->with('actor')
            ->where(function (Builder $query): void {
                $query->where('on_behalf_of_user_id', $this->userId)
                    ->orWhere('actor_user_id', $this->userId)
                    ->orWhere(fn (Builder $query) => $query->where('subject_type', (new User)->getMorphClass())->where('subject_id', $this->userId));
            })
            ->latest('id')
            ->limit(10)
            ->get();
    }

    public function save(UpdateUserByAdmin $action): void
    {
        $user = $this->user;
        $this->authorize('update', $user);

        $this->form->validate();

        $action->handle($user, $this->actor(), $this->form->toAttributes());

        unset($this->user, $this->auditEntries);
        $this->form->fillFromUser($this->user);

        Flux::toast(variant: 'success', text: __('Account updated.'));
    }

    public function sendPasswordLink(SendSetPasswordLink $action): void
    {
        $user = $this->user;
        $this->authorize('sendPasswordLink', $user);

        $action->handle($user, $this->actor());

        unset($this->auditEntries);

        Flux::toast(variant: 'success', text: __('Email sent to :email.', ['email' => $user->email]));
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}; ?>

@php
    $user = $this->user;
@endphp

<div class="flex flex-col gap-8">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('admin.users.index')" wire:navigate>{{ __('Users') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $user->name }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="flex flex-col gap-2">
            <div class="flex flex-wrap items-center gap-2">
                <flux:badge size="sm" :color="$user->isSuperadmin() ? 'zinc' : 'lime'">{{ $user->role->label() }}</flux:badge>
                @if ($user->is_assisted)
                    <flux:badge size="sm" color="blue" icon="phone">{{ __('Assisted account') }}</flux:badge>
                @endif
                @if ($user->email_verified_at === null)
                    <flux:badge size="sm" color="amber">{{ __('Email not verified') }}</flux:badge>
                @endif
            </div>
            <flux:heading size="xl" level="1">{{ $user->name }}</flux:heading>
            <flux:text>{{ $user->email }} @if ($user->phone) · {{ $user->phone }} @endif</flux:text>
            <flux:text size="sm" class="text-slate">{{ __('Registered on :date', ['date' => $user->created_at?->translatedFormat('j M Y, H:i')]) }}</flux:text>
        </div>

        <div class="flex flex-wrap gap-2">
            <flux:button icon="building-storefront" :href="route('admin.businesses.create', ['propietario' => $user->id])" wire:navigate>{{ __('New business for this user') }}</flux:button>
            <flux:button icon="document-plus" :href="route('admin.listings.create', ['propietario' => $user->id])" wire:navigate>{{ __('New listing for this user') }}</flux:button>
            <flux:button icon="envelope" wire:click="sendPasswordLink" wire:confirm="{{ __('Send the set-password email to :email?', ['email' => $user->email]) }}">{{ __('Send set-password link') }}</flux:button>
        </div>
    </div>

    <div class="grid gap-8 lg:grid-cols-[2fr_1fr]">
        <div class="flex flex-col gap-8">
            <flux:card class="flex flex-col gap-4">
                <flux:heading size="lg" level="2">{{ __('Businesses') }}</flux:heading>
                @if ($this->businesses->isEmpty())
                    <flux:text>{{ __('This user does not own any business yet.') }}</flux:text>
                @else
                    <ul class="flex flex-col divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->businesses as $business)
                            <li class="flex items-center justify-between gap-3 py-2" wire:key="business-{{ $business->id }}">
                                <div class="flex min-w-0 flex-col">
                                    <flux:link :href="route('admin.businesses.edit', $business)" wire:navigate class="truncate">{{ $business->name }}</flux:link>
                                    <span class="truncate text-xs text-slate">{{ $business->category->name }} · {{ $business->business_type->label() }}</span>
                                </div>
                                @if ($business->openListing)
                                    <x-listing-status-badge :listing="$business->openListing" />
                                @else
                                    <flux:button size="sm" variant="ghost" icon="plus" :href="route('admin.listings.create', ['empresa' => $business->id])" wire:navigate>{{ __('New listing') }}</flux:button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </flux:card>

            <flux:card class="flex flex-col gap-4">
                <flux:heading size="lg" level="2">{{ __('Listings') }}</flux:heading>
                @if ($this->listings->isEmpty())
                    <flux:text>{{ __('No listings yet.') }}</flux:text>
                @else
                    <ul class="flex flex-col divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->listings as $listing)
                            <li class="flex items-center justify-between gap-3 py-2" wire:key="listing-{{ $listing->id }}">
                                <div class="flex min-w-0 flex-col">
                                    <flux:link :href="route('admin.listings.show', $listing)" wire:navigate class="truncate">{{ $listing->title ?? __('Untitled draft') }}</flux:link>
                                    <span class="truncate text-xs text-slate">{{ $listing->business->name }}@if ($listing->last_confirmed_at) · {{ __('Confirmed :date', ['date' => $listing->last_confirmed_at->translatedFormat('j M Y')]) }}@endif</span>
                                </div>
                                <x-listing-status-badge :listing="$listing" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </flux:card>

            <flux:card class="flex flex-col gap-4">
                <div class="flex items-center justify-between gap-4">
                    <flux:heading size="lg" level="2">{{ __('Audit trail') }}</flux:heading>
                    <flux:link :href="route('admin.audit.index', ['usuario' => $user->id])" wire:navigate class="text-sm">{{ __('See all') }}</flux:link>
                </div>
                @if ($this->auditEntries->isEmpty())
                    <flux:text>{{ __('No administrative action involves this account yet.') }}</flux:text>
                @else
                    <ul class="flex flex-col gap-2 text-sm">
                        @foreach ($this->auditEntries as $entry)
                            <li class="flex flex-col" wire:key="audit-{{ $entry->id }}">
                                <span class="font-medium text-ink dark:text-white">{{ AuditActions::label($entry->action) }}</span>
                                <span class="text-xs text-slate">{{ $entry->created_at?->translatedFormat('j M Y, H:i') }} · {{ $entry->actor?->name ?? __('System') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </flux:card>
        </div>

        <div class="flex flex-col gap-8">
            <flux:card class="flex flex-col gap-4">
                <flux:heading size="lg" level="2">{{ __('Account data') }}</flux:heading>
                <flux:text size="sm">{{ __('Changes are recorded in the audit log. The role can only be changed from the command line.') }}</flux:text>
                <form wire:submit="save" class="flex flex-col gap-4">
                    <flux:input wire:model="form.name" :label="__('Name')" maxlength="255" />
                    <flux:input wire:model="form.email" type="email" :label="__('Email')" maxlength="255" />
                    <flux:input wire:model="form.phone" type="tel" :label="__('Phone')" maxlength="30" />
                    <div>
                        <flux:button type="submit" variant="primary" size="sm">{{ __('Save') }}</flux:button>
                    </div>
                </form>
            </flux:card>
        </div>
    </div>
</div>
