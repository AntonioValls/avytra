<?php

use App\Actions\Contact\MarkContactRequestAsRead;
use App\Models\ContactRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Messages relayed from the public listing pages (docs/12, ADR-019): the inbox of every
 * listing the user owns. Opening a message marks it as read; answering happens from the
 * user's own mail client through a mailto link.
 */
new class extends Component {
    use WithPagination;

    #[Locked]
    public ?int $openId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', ContactRequest::class);
    }

    public function rendering(View $view): void
    {
        $view->title(__('Messages'));
    }

    /**
     * @return LengthAwarePaginator<int, ContactRequest>
     */
    #[Computed]
    public function messages(): LengthAwarePaginator
    {
        return ContactRequest::query()
            ->receivedBy(Auth::user())
            ->with('listing')
            ->orderByRaw('(read_at IS NULL) DESC')
            ->latest('id')
            ->paginate(config('avytra.pagination.panel_cards'));
    }

    #[Computed]
    public function current(): ?ContactRequest
    {
        return $this->openId === null ? null : ContactRequest::query()->with('listing')->find($this->openId);
    }

    public function open(int $requestId, MarkContactRequestAsRead $action): void
    {
        $request = ContactRequest::query()->with('listing.business')->findOrFail($requestId);
        $this->authorize('view', $request);

        if (! $request->isRead()) {
            $this->authorize('markAsRead', $request);
            $action->handle($request);
            unset($this->messages);
        }

        $this->openId = $request->id;
        unset($this->current);
    }

    public function close(): void
    {
        $this->openId = null;
        unset($this->current);
    }

    public function replyHref(ContactRequest $request): string
    {
        return 'mailto:'.$request->sender_email.'?subject='.rawurlencode(__('Re: :title — AVYTRA', ['title' => (string) $request->listing->title]));
    }
}; ?>

<div class="flex flex-col gap-8">
    <div class="flex flex-col gap-1">
        <flux:heading size="xl" level="1">{{ __('Messages') }}</flux:heading>
        <flux:text>{{ __('What buyers send you from your listings. You also receive each message by email; answer from there or from the reply button.') }}</flux:text>
    </div>

    @if ($this->messages->isEmpty())
        <x-empty-state
            icon="envelope"
            :heading="__('You have no messages yet.')"
            :text="__('When somebody writes to you from one of your published listings, the message will appear here and in your inbox.')"
        />
    @else
        @php $current = $this->current; @endphp
        <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
            {{-- On phones the list hides while a message is open; on desktop both columns stay. --}}
            <div @class(['flex flex-col gap-3', 'max-lg:hidden' => $current !== null])>
                <div class="flex flex-col divide-y divide-zinc-100 rounded-lg border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-700">
                    @foreach ($this->messages as $message)
                        <button
                            type="button"
                            wire:key="message-{{ $message->id }}"
                            wire:click="open({{ $message->id }})"
                            @class([
                                'flex w-full flex-col gap-1 px-4 py-3 text-start transition hover:bg-mist focus-visible:outline-2 focus-visible:outline-transfer dark:hover:bg-zinc-900',
                                'bg-mist dark:bg-zinc-900' => $openId === $message->id,
                            ])
                        >
                            <div class="flex items-center justify-between gap-3">
                                <span @class(['truncate', 'font-semibold text-ink dark:text-white' => ! $message->isRead(), 'text-ink/80 dark:text-zinc-200' => $message->isRead()])>
                                    {{ $message->sender_name }}
                                </span>
                                <span class="shrink-0 text-xs text-slate">{{ $message->created_at?->translatedFormat('j M') }}</span>
                            </div>
                            <span class="truncate text-sm text-slate">{{ $message->listing->title }}</span>
                            <span class="line-clamp-2 text-sm text-ink/80 dark:text-zinc-300">{{ $message->message }}</span>
                            @if (! $message->isRead() || ! $message->wasDelivered())
                                <div class="flex flex-wrap gap-1 pt-1">
                                    @unless ($message->isRead())
                                        <flux:badge size="sm" color="lime">{{ __('New') }}</flux:badge>
                                    @endunless
                                    @unless ($message->wasDelivered())
                                        <flux:badge size="sm" color="amber" icon="exclamation-triangle">{{ __('The email could not be delivered') }}</flux:badge>
                                    @endunless
                                </div>
                            @endif
                        </button>
                    @endforeach
                </div>

                <flux:pagination :paginator="$this->messages" />
            </div>

            <div @class(['max-lg:hidden' => $current === null])>
                @if ($current === null)
                    <flux:card class="flex flex-col items-center gap-2 py-16 text-center">
                        <flux:icon.envelope-open class="size-8 text-slate" />
                        <flux:text>{{ __('Select a message to read it.') }}</flux:text>
                    </flux:card>
                @else
                    <flux:card class="flex flex-col gap-4" wire:key="detail-{{ $current->id }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 flex-col gap-1">
                                <flux:heading size="lg">{{ $current->sender_name }}</flux:heading>
                                <flux:text size="sm">
                                    {{ __('About') }}
                                    <flux:link :href="route('listings.show', $current->listing->slug)" target="_blank">{{ $current->listing->title }}</flux:link>
                                    · {{ $current->created_at?->translatedFormat('j \d\e F Y, H:i') }}
                                </flux:text>
                            </div>
                            <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="close" :aria-label="__('Close')" />
                        </div>

                        @unless ($current->wasDelivered())
                            <flux:callout icon="exclamation-triangle" variant="warning">
                                <flux:callout.heading>{{ __('The email could not be delivered') }}</flux:callout.heading>
                                <flux:callout.text>{{ __('We could not send this message to your inbox. You can still read it here and answer the sender.') }}</flux:callout.text>
                            </flux:callout>
                        @endunless

                        <dl class="grid gap-2 text-sm sm:grid-cols-2">
                            <div><dt class="text-slate">{{ __('Email') }}</dt><dd class="break-all">{{ $current->sender_email }}</dd></div>
                            @if ($current->sender_phone)
                                <div><dt class="text-slate">{{ __('Phone') }}</dt><dd>{{ $current->sender_phone }}</dd></div>
                            @endif
                        </dl>

                        <div class="rounded-lg bg-mist p-4 text-sm leading-relaxed whitespace-pre-line dark:bg-zinc-900">{{ $current->message }}</div>

                        <div class="flex flex-wrap gap-2">
                            <flux:button variant="primary" icon="arrow-uturn-left" :href="$this->replyHref($current)">{{ __('Reply by email') }}</flux:button>
                            @if ($current->sender_phone)
                                <flux:button variant="outline" icon="phone" :href="'tel:'.$current->sender_phone">{{ __('Call') }}</flux:button>
                            @endif
                        </div>
                    </flux:card>
                @endif
            </div>
        </div>
    @endif
</div>
