<?php

use App\Models\ContactRequest;
use App\Models\Listing;
use App\Models\ListingReport;
use App\Models\User;
use App\Support\Monitoring\SchedulerHeartbeat;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Operational summary (docs/09): what needs the superadmin's attention today. Numbers and
 * short lists, no charts. Freshness lists come from docs/13 ("Fallos y reintentos").
 */
new class extends Component {
    private const LIST_LIMIT = 8;

    public function mount(): void
    {
        $this->authorize('viewAny', Listing::class);
    }

    public function rendering(View $view): void
    {
        $view->title(__('Operational summary'));
    }

    /**
     * @return Collection<int, Listing>
     */
    #[Computed]
    public function needingConfirmation(): Collection
    {
        return Listing::query()->needingConfirmation()->with('business.owner')->oldest('last_confirmed_at')->limit(self::LIST_LIMIT)->get();
    }

    #[Computed]
    public function needingConfirmationCount(): int
    {
        return Listing::query()->needingConfirmation()->count();
    }

    /**
     * @return Collection<int, Listing>
     */
    #[Computed]
    public function expiredRecently(): Collection
    {
        return Listing::query()->expiredRecently()->with('business.owner')->latest('expired_at')->limit(self::LIST_LIMIT)->get();
    }

    #[Computed]
    public function expiredRecentlyCount(): int
    {
        return Listing::query()->expiredRecently()->count();
    }

    /**
     * @return Collection<int, Listing>
     */
    #[Computed]
    public function failedReminders(): Collection
    {
        return Listing::query()->withFailedReminder()->with('business.owner')->latest('updated_at')->limit(self::LIST_LIMIT)->get();
    }

    #[Computed]
    public function failedRemindersCount(): int
    {
        return Listing::query()->withFailedReminder()->count();
    }

    #[Computed]
    public function openReportsCount(): int
    {
        return ListingReport::query()->open()->count();
    }

    #[Computed]
    public function undeliveredMessagesCount(): int
    {
        return ContactRequest::query()->undelivered()->count();
    }

    #[Computed]
    public function assistedAccountsCount(): int
    {
        return User::query()->where('is_assisted', true)->count();
    }

    #[Computed]
    public function schedulerHealthy(): bool
    {
        return app(SchedulerHeartbeat::class)->isHealthy();
    }

    #[Computed]
    public function schedulerLastBeat(): ?string
    {
        return app(SchedulerHeartbeat::class)->lastBeatAt()?->diffForHumans();
    }

    /**
     * The superadmin account is expected to use 2FA (docs/16): a reminder, not a lock.
     */
    #[Computed]
    public function needsTwoFactor(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return ! $user->hasEnabledTwoFactorAuthentication();
    }

    /**
     * @return Collection<int, Listing>
     */
    #[Computed]
    public function latestListings(): Collection
    {
        return Listing::query()->whereNotNull('published_at')->with('business.owner')->latest('published_at')->limit(5)->get();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function latestUsers(): Collection
    {
        return User::query()->latest('id')->limit(5)->get();
    }
}; ?>

<div class="flex flex-col gap-8">
    <div class="flex flex-col gap-1">
        <flux:heading size="xl" level="1">{{ __('Operational summary') }}</flux:heading>
        <flux:text>{{ __('What needs attention today. Every action is taken from the listing detail.') }}</flux:text>
    </div>

    @if (! $this->schedulerHealthy)
        <flux:callout icon="exclamation-triangle" variant="danger">
            <flux:callout.heading>{{ __('The task scheduler is not running.') }}</flux:callout.heading>
            <flux:callout.text>
                {{ $this->schedulerLastBeat === null ? __('No heartbeat has been recorded yet.') : __('Last heartbeat: :time.', ['time' => $this->schedulerLastBeat]) }}
                {{ __('Without it, reminders are not sent and listings are not paused automatically. Check the cron entry for “php artisan schedule:run”.') }}
            </flux:callout.text>
        </flux:callout>
    @endif

    @if ($this->needsTwoFactor)
        <flux:callout icon="shield-exclamation" variant="warning">
            <flux:callout.heading>{{ __('Protect the superadmin account with two-factor authentication.') }}</flux:callout.heading>
            <flux:callout.text>{{ __('This account can act on every listing and user. Enable 2FA from the security settings.') }}</flux:callout.text>
            <x-slot name="actions">
                <flux:button size="sm" variant="primary" icon="shield-check" :href="route('security.edit')" wire:navigate>{{ __('Enable 2FA') }}</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <flux:card class="flex flex-col gap-1">
            <flux:text size="sm">{{ __('Needs confirmation') }}</flux:text>
            <flux:heading size="xl">{{ $this->needingConfirmationCount }}</flux:heading>
            <flux:link :href="route('admin.listings.index', ['condicion' => 'needs_confirmation'])" wire:navigate class="text-sm">{{ __('See all') }}</flux:link>
        </flux:card>
        <flux:card class="flex flex-col gap-1">
            <flux:text size="sm">{{ __('Paused automatically in the last :days days', ['days' => (int) config('avytra.freshness.expired_review_days')]) }}</flux:text>
            <flux:heading size="xl">{{ $this->expiredRecentlyCount }}</flux:heading>
            <flux:link :href="route('admin.listings.index', ['condicion' => 'expired_recently'])" wire:navigate class="text-sm">{{ __('See all') }}</flux:link>
        </flux:card>
        <flux:card class="flex flex-col gap-1">
            <flux:text size="sm">{{ __('Undelivered reminders') }}</flux:text>
            <flux:heading size="xl">{{ $this->failedRemindersCount }}</flux:heading>
            <flux:link :href="route('admin.listings.index', ['condicion' => 'failed_reminder'])" wire:navigate class="text-sm">{{ __('See all') }}</flux:link>
        </flux:card>
        <flux:card class="flex flex-col gap-1">
            <flux:text size="sm">{{ __('Open reports') }}</flux:text>
            <flux:heading size="xl">{{ $this->openReportsCount }}</flux:heading>
            <flux:link :href="route('admin.reports.index')" wire:navigate class="text-sm">{{ __('See all') }}</flux:link>
        </flux:card>
        <flux:card class="flex flex-col gap-1">
            <flux:text size="sm">{{ __('Assisted accounts') }}</flux:text>
            <flux:heading size="xl">{{ $this->assistedAccountsCount }}</flux:heading>
            <flux:link :href="route('admin.users.index', ['condicion' => 'assisted'])" wire:navigate class="text-sm">{{ __('See all') }}</flux:link>
        </flux:card>
        <flux:card class="flex flex-col gap-1">
            <flux:text size="sm">{{ __('Undelivered messages') }}</flux:text>
            <flux:heading size="xl">{{ $this->undeliveredMessagesCount }}</flux:heading>
            <flux:text size="sm" class="text-slate">{{ __('Relayed messages whose email failed; the owner still sees them in their panel.') }}</flux:text>
        </flux:card>
    </div>

    <div class="grid gap-8 lg:grid-cols-2">
        <flux:card class="flex flex-col gap-4">
            <flux:heading size="lg" level="2">{{ __('Needs confirmation') }}</flux:heading>
            @if ($this->needingConfirmation->isEmpty())
                <flux:text>{{ __('Every published listing was confirmed recently.') }}</flux:text>
            @else
                <ul class="flex flex-col divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($this->needingConfirmation as $listing)
                        <li class="flex items-center justify-between gap-3 py-2" wire:key="needing-{{ $listing->id }}">
                            <div class="flex min-w-0 flex-col">
                                <flux:link :href="route('admin.listings.show', $listing)" wire:navigate class="truncate">{{ $listing->title }}</flux:link>
                                <span class="truncate text-xs text-slate">{{ $listing->business->owner->name }} · {{ __('Pause on :date', ['date' => $listing->next_confirmation_at?->translatedFormat('j M')]) }}</span>
                            </div>
                            <flux:badge size="sm" color="amber">{{ __(':days days', ['days' => $listing->daysSinceConfirmation()]) }}</flux:badge>
                        </li>
                    @endforeach
                </ul>
            @endif
        </flux:card>

        <flux:card class="flex flex-col gap-4">
            <flux:heading size="lg" level="2">{{ __('Paused automatically') }}</flux:heading>
            @if ($this->expiredRecently->isEmpty())
                <flux:text>{{ __('No listing was paused for lack of confirmation in the last :days days.', ['days' => (int) config('avytra.freshness.expired_review_days')]) }}</flux:text>
            @else
                <ul class="flex flex-col divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($this->expiredRecently as $listing)
                        <li class="flex items-center justify-between gap-3 py-2" wire:key="expired-{{ $listing->id }}">
                            <div class="flex min-w-0 flex-col">
                                <flux:link :href="route('admin.listings.show', $listing)" wire:navigate class="truncate">{{ $listing->title }}</flux:link>
                                <span class="truncate text-xs text-slate">{{ $listing->business->owner->name }} · {{ $listing->business->owner->email }}</span>
                            </div>
                            <span class="shrink-0 text-xs text-slate">{{ $listing->expired_at?->translatedFormat('j M') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </flux:card>

        <flux:card class="flex flex-col gap-4">
            <flux:heading size="lg" level="2">{{ __('Undelivered reminders') }}</flux:heading>
            @if ($this->failedReminders->isEmpty())
                <flux:text>{{ __('Every reminder was delivered.') }}</flux:text>
            @else
                <ul class="flex flex-col divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($this->failedReminders as $listing)
                        <li class="flex items-center justify-between gap-3 py-2" wire:key="failed-{{ $listing->id }}">
                            <div class="flex min-w-0 flex-col">
                                <flux:link :href="route('admin.listings.show', $listing)" wire:navigate class="truncate">{{ $listing->title }}</flux:link>
                                <span class="truncate text-xs text-slate">{{ $listing->business->owner->name }} · {{ $listing->business->owner->email }}</span>
                            </div>
                            <flux:button size="sm" variant="ghost" icon="arrow-right" :href="route('admin.listings.show', $listing)" wire:navigate>{{ __('Resend') }}</flux:button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </flux:card>

        <flux:card class="flex flex-col gap-4">
            <flux:heading size="lg" level="2">{{ __('Latest activity') }}</flux:heading>
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-2">
                    <flux:text size="sm" class="font-semibold">{{ __('Latest published listings') }}</flux:text>
                    @if ($this->latestListings->isEmpty())
                        <flux:text size="sm">{{ __('Nothing published yet.') }}</flux:text>
                    @else
                        <ul class="flex flex-col gap-1 text-sm">
                            @foreach ($this->latestListings as $listing)
                                <li class="flex items-center justify-between gap-3" wire:key="latest-{{ $listing->id }}">
                                    <flux:link :href="route('admin.listings.show', $listing)" wire:navigate class="truncate">{{ $listing->title }}</flux:link>
                                    <span class="shrink-0 text-xs text-slate">{{ $listing->published_at?->translatedFormat('j M') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <div class="flex flex-col gap-2">
                    <flux:text size="sm" class="font-semibold">{{ __('Latest users') }}</flux:text>
                    <ul class="flex flex-col gap-1 text-sm">
                        @foreach ($this->latestUsers as $user)
                            <li class="flex items-center justify-between gap-3" wire:key="user-{{ $user->id }}">
                                <span class="truncate">{{ $user->name }} <span class="text-xs text-slate">· {{ $user->email }}</span></span>
                                <span class="shrink-0 text-xs text-slate">{{ $user->created_at?->translatedFormat('j M') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </flux:card>
    </div>
</div>
