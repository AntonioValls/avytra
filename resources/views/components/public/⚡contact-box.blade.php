<?php

use App\Enums\ContactMethod;
use App\Models\Listing;
use App\Support\Listings\PublicListingPresenter;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Contact the owner" block (docs/12). Phone, WhatsApp and email are absent from the initial
 * HTML and are printed only after "Show", within a per-IP rate limit. The values are never
 * stored in component state: they are read from the presenter at render time.
 */
new class extends Component {
    #[Locked]
    public int $listingId;

    public bool $revealed = false;

    public function mount(int $listingId): void
    {
        $this->listingId = $listingId;
    }

    public function reveal(): void
    {
        $listing = $this->listing();

        abort_unless($this->canBeContacted($listing), 404);

        $key = 'contact-reveal:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, (int) config('avytra.contact.reveal_rate_limit_per_hour'))) {
            Flux::toast(variant: 'warning', text: __('Too many requests. Try again in a while.'));

            return;
        }

        RateLimiter::hit($key, 3600);

        $this->revealed = true;
    }

    private function listing(): Listing
    {
        return Listing::query()->with(PublicListingPresenter::RELATIONS)->findOrFail($this->listingId);
    }

    /**
     * Public listings, or private ones seen by their owner or the superadmin.
     */
    private function canBeContacted(Listing $listing): bool
    {
        if ($listing->isPubliclyVisible()) {
            return true;
        }

        $user = Auth::user();

        return $user !== null && $user->can('view', $listing);
    }

    /**
     * Prefilled subject and message for mail and WhatsApp links.
     */
    private function intro(PublicListingPresenter $listing): string
    {
        return __('Interested in: :title — AVYTRA', ['title' => $listing->title()]);
    }

    public function with(): array
    {
        $listing = $this->listing();
        $presenter = PublicListingPresenter::for($listing);
        $revealed = $this->revealed && $this->canBeContacted($listing);

        $channels = [];

        foreach ($presenter->contactMethods() as $method) {
            $value = PublicListingPresenter::isSensitiveChannel($method)
                ? ($revealed ? $presenter->sensitiveChannel($method) : null)
                : $presenter->publicChannel($method);

            $channels[] = [
                'method' => $method,
                'sensitive' => PublicListingPresenter::isSensitiveChannel($method),
                'value' => $value,
                'href' => $value === null ? null : match ($method) {
                    ContactMethod::Email => 'mailto:'.$value.'?subject='.rawurlencode($this->intro($presenter)),
                    ContactMethod::Phone => 'tel:'.$value,
                    ContactMethod::Whatsapp => 'https://wa.me/'.preg_replace('/\D+/', '', $value).'?text='.rawurlencode($this->intro($presenter)),
                    ContactMethod::Website, ContactMethod::ExternalForm => $value,
                    ContactMethod::Other => null,
                },
            ];
        }

        return [
            'contactName' => $presenter->contactName(),
            'contactNotes' => $presenter->contactNotes(),
            'channels' => $channels,
        ];
    }
}; ?>

<flux:card class="flex flex-col gap-4">
    <div class="flex flex-col gap-1">
        <flux:heading size="lg">{{ __('Contact the owner') }}</flux:heading>
        @if ($contactName)
            <flux:text class="font-semibold text-ink">{{ $contactName }}</flux:text>
        @endif
    </div>

    <div class="flex flex-col gap-2">
        @foreach ($channels as $channel)
            @php $method = $channel['method']; @endphp
            <div wire:key="channel-{{ $method->value }}">
                @if ($channel['sensitive'] && $channel['value'] === null)
                    <flux:button
                        :variant="$loop->first ? 'primary' : 'outline'"
                        :icon="$method->icon()"
                        class="w-full"
                        wire:click="reveal"
                        wire:loading.attr="disabled"
                    >
                        {{ match ($method) { \App\Enums\ContactMethod::Email => __('Show email'), \App\Enums\ContactMethod::Whatsapp => __('Show WhatsApp'), default => __('Show phone') } }}
                    </flux:button>
                @elseif ($method === \App\Enums\ContactMethod::Other)
                    <div class="flex items-start gap-2 rounded-md border border-zinc-200 p-3 text-sm">
                        <flux:icon.information-circle variant="mini" class="mt-0.5 shrink-0 text-transfer" />
                        <span>{{ $channel['value'] }}</span>
                    </div>
                @else
                    <flux:button
                        :variant="$loop->first ? 'primary' : 'outline'"
                        :icon="$method->icon()"
                        class="w-full"
                        :href="$channel['href']"
                        :target="in_array($method, [\App\Enums\ContactMethod::Website, \App\Enums\ContactMethod::ExternalForm, \App\Enums\ContactMethod::Whatsapp], true) ? '_blank' : null"
                        rel="nofollow noopener"
                    >
                        {{ $method->actionLabel() }}
                        @if ($channel['sensitive'])
                            <span class="ms-1 font-normal">· {{ $channel['value'] }}</span>
                        @endif
                    </flux:button>
                @endif
            </div>
        @endforeach
    </div>

    @if ($contactNotes)
        <flux:text size="sm">{{ $contactNotes }}</flux:text>
    @endif

    <flux:text size="sm" class="text-slate">{{ __('When you get in touch, mention that you saw this listing on AVYTRA.') }}</flux:text>
</flux:card>
