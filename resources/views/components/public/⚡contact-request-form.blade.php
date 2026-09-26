<?php

use App\Actions\Contact\SubmitContactRequest;
use App\Enums\ListingStatus;
use App\Models\Listing;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Send a message" modal on the listing page (docs/12, ADR-019). The message is relayed by
 * email to the seller; the seller's address never reaches the visitor. Honeypot, minimum
 * time to submit, per-IP rate limit and a daily cap per listing and visitor.
 */
new class extends Component {
    #[Locked]
    public int $listingId;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $message = '';

    /** Honeypot: humans never see this field, bots fill it. */
    public string $website = '';

    #[Locked]
    public int $openedAt = 0;

    public function mount(int $listingId): void
    {
        $this->listingId = $listingId;
        $this->openedAt = now()->getTimestamp();

        $user = Auth::user();

        if ($user !== null) {
            $this->name = $user->name;
            $this->email = $user->email;
        }
    }

    public function submit(SubmitContactRequest $action): void
    {
        $listing = Listing::query()->with('business.owner')->findOrFail($this->listingId);

        abort_unless($listing->status === ListingStatus::Published, 404);

        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'message' => ['required', 'string', 'min:10', 'max:'.config('avytra.contact.request_message_max_length')],
        ], [], ['name' => __('name'), 'email' => __('email'), 'phone' => __('phone'), 'message' => __('message')]);

        // Bots: pretend it worked and drop it.
        $tooFast = now()->getTimestamp() - $this->openedAt < (int) config('avytra.contact.request_min_seconds_to_submit');

        if ($this->website !== '' || $tooFast) {
            $this->finish();

            return;
        }

        $ip = request()->ip();
        $key = 'contact-request:'.$ip;

        if (RateLimiter::tooManyAttempts($key, (int) config('avytra.contact.request_rate_limit_per_hour'))) {
            $this->addError('message', __('You have sent several messages recently. Please try again later.'));

            return;
        }

        if (SubmitContactRequest::sentToday($listing, $ip) >= (int) config('avytra.contact.requests_per_listing_per_day')) {
            $this->addError('message', __('You have already written to this seller today. Wait for their answer before sending more.'));

            return;
        }

        RateLimiter::hit($key, 3600);

        $action->handle($listing, Auth::user(), $ip, [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'message' => $this->message,
        ]);

        $this->finish();
    }

    private function finish(): void
    {
        $this->reset('phone', 'message', 'website');
        Flux::modal('contact-request')->close();
        Flux::toast(variant: 'success', heading: __('Message sent'), text: __('The seller has received your message and will answer you by email.'));
    }
}; ?>

<div>
    <flux:modal name="contact-request" class="md:w-[30rem]">
        <form wire:submit="submit" class="flex flex-col gap-6">
            <div class="flex flex-col gap-1">
                <flux:heading size="lg">{{ __('Send a message') }}</flux:heading>
                <flux:text>{{ __('AVYTRA forwards your message to the seller. They will answer you directly by email.') }}</flux:text>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="name" :label="__('Your name')" maxlength="120" autocomplete="name" />
                <flux:input wire:model="email" type="email" :label="__('Your email')" maxlength="255" autocomplete="email" />
            </div>

            <flux:input wire:model="phone" type="tel" :label="__('Your phone (optional)')" :description="__('Only if you prefer to be called.')" maxlength="30" autocomplete="tel" />

            <flux:textarea wire:model="message" :label="__('Message')" rows="5" :maxlength="config('avytra.contact.request_message_max_length')" :placeholder="__('Introduce yourself and say what you would like to know.')" />

            {{-- Honeypot: hidden from people, tempting for bots. --}}
            <div class="hidden" aria-hidden="true">
                <label>{{ __('Website') }} <input type="text" wire:model="website" tabindex="-1" autocomplete="off" /></label>
            </div>

            <flux:text size="sm" class="text-slate">{{ __('Your details are only shared with the seller of this listing so they can answer you.') }}</flux:text>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="paper-airplane" wire:loading.attr="disabled">{{ __('Send message') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
