<?php

use App\Actions\Reports\SubmitListingReport;
use App\Enums\ListingReportReason;
use App\Models\Listing;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * "Report this listing" link and modal (docs/08). Honeypot, minimum time to submit,
 * per-IP rate limit and at most one open report per listing and visitor.
 */
new class extends Component {
    #[Locked]
    public int $listingId;

    public string $reason = '';

    public string $message = '';

    public string $email = '';

    /** Honeypot: humans never see this field, bots fill it. */
    public string $website = '';

    #[Locked]
    public int $openedAt = 0;

    public function mount(int $listingId): void
    {
        $this->listingId = $listingId;
        $this->openedAt = now()->getTimestamp();
    }

    public function submit(SubmitListingReport $action): void
    {
        $listing = Listing::query()->findOrFail($this->listingId);

        abort_unless($listing->isPubliclyVisible(), 404);

        $user = Auth::user();

        $this->validate([
            'reason' => ['required', Rule::enum(ListingReportReason::class)],
            'message' => ['nullable', 'string', 'max:'.config('avytra.reports.message_max_length')],
            'email' => [Rule::requiredIf($user === null), 'nullable', 'email:rfc', 'max:255'],
        ], [], ['reason' => __('reason'), 'message' => __('message'), 'email' => __('email')]);

        // Bots: pretend it worked and drop it.
        $tooFast = now()->getTimestamp() - $this->openedAt < (int) config('avytra.reports.min_seconds_to_submit');

        if ($this->website !== '' || $tooFast) {
            $this->finish();

            return;
        }

        $ip = request()->ip();
        $key = 'report:'.$ip;

        if (RateLimiter::tooManyAttempts($key, (int) config('avytra.reports.rate_limit_per_hour'))) {
            $this->addError('reason', __('You have sent several reports recently. Please try again later.'));

            return;
        }

        if (SubmitListingReport::hasOpenReport($listing, $user, $ip)) {
            $this->addError('reason', __('You already reported this listing. We are reviewing it.'));

            return;
        }

        RateLimiter::hit($key, 3600);

        $action->handle($listing, $user, $ip, [
            'reason' => ListingReportReason::from($this->reason),
            'message' => $this->message,
            'email' => $this->email,
        ]);

        $this->finish();
    }

    private function finish(): void
    {
        $this->reset('reason', 'message', 'email', 'website');
        Flux::modal('report-listing')->close();
        Flux::toast(variant: 'success', heading: __('Thank you'), text: __('We have received your report and will review the listing.'));
    }
}; ?>

<div>
    <flux:modal.trigger name="report-listing">
        <flux:button size="sm" variant="ghost" icon="flag">{{ __('Report this listing') }}</flux:button>
    </flux:modal.trigger>

    <flux:modal name="report-listing" class="md:w-[28rem]">
        <form wire:submit="submit" class="flex flex-col gap-6">
            <div class="flex flex-col gap-1">
                <flux:heading size="lg">{{ __('Report this listing') }}</flux:heading>
                <flux:text>{{ __('Tell us what is wrong. We review every report and act if needed.') }}</flux:text>
            </div>

            <flux:radio.group wire:model="reason" :label="__('Reason')">
                @foreach (\App\Enums\ListingReportReason::cases() as $option)
                    <flux:radio :value="$option->value" :label="$option->label()" />
                @endforeach
            </flux:radio.group>

            <flux:textarea wire:model="message" :label="__('Message (optional)')" rows="3" :maxlength="config('avytra.reports.message_max_length')" />

            @guest
                <flux:input wire:model="email" type="email" :label="__('Your email')" :description="__('Only to answer you if we need more details. It is never published.')" />
            @endguest

            {{-- Honeypot: hidden from people, tempting for bots. --}}
            <div class="hidden" aria-hidden="true">
                <label>{{ __('Website') }} <input type="text" wire:model="website" tabindex="-1" autocomplete="off" /></label>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="flag">{{ __('Send report') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
