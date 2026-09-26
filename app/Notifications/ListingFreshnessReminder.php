<?php

namespace App\Notifications;

use App\Enums\ListingEventType;
use App\Enums\ReminderStage;
use App\Models\Listing;
use App\Models\User;
use App\Support\Listings\ConfirmationLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Throwable;

/**
 * "Is it still available?" (docs/13). Two stages: a plain question at first_reminder_days
 * and a heads-up with the exact pause date at second_reminder_days. Both link to the
 * authenticated confirmation page with a temporary signature.
 */
class ListingFreshnessReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(public Listing $listing, public ReminderStage $stage) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $title = (string) $this->listing->title;
        $pauseDate = $this->listing->next_confirmation_at?->translatedFormat('j \d\e F');
        $daysLeft = $this->listing->next_confirmation_at === null
            ? 0
            : max(0, (int) now()->startOfDay()->diffInDays($this->listing->next_confirmation_at->startOfDay(), false));

        $message = (new MailMessage)
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]));

        if ($this->stage === ReminderStage::First) {
            $message
                ->subject(__('Is “:title” still available?', ['title' => $title]))
                ->line(__('It has been a while since you confirmed that “:title” is still available. Buyers rely on that confirmation.', ['title' => $title]))
                ->line(__('If it is, one click is enough. If we do not hear from you, the listing will be paused on :date. Nothing is deleted.', ['date' => $pauseDate]));
        } else {
            $message
                ->subject(__('“:title” will be paused in :days days', ['title' => $title, 'days' => $daysLeft]))
                ->line(__('We still have not been able to confirm that “:title” is available.', ['title' => $title]))
                ->line(__('Unless you confirm it, the listing will be paused on :date and stop appearing in search results. Your data stays intact and you can bring it back at any time.', ['date' => $pauseDate]));
        }

        return $message
            ->action(__('Yes, it is still available'), ConfirmationLink::for($this->listing))
            ->line(__('You will be asked to sign in first so the confirmation is yours. The link works for :days days; after that you can confirm from your panel.', ['days' => (int) config('avytra.freshness.confirmation_link_ttl_days')]))
            ->salutation(__('The AVYTRA team'));
    }

    /**
     * The sent_at flag is already set, so nothing retries automatically: the failure goes to the
     * listing history for the superadmin to resend by hand (docs/13, "Fallos y reintentos").
     */
    public function failed(Throwable $exception): void
    {
        $this->listing->events()->create([
            'type' => ListingEventType::ReminderFailed,
            'actor_user_id' => null,
            'on_behalf_of_user_id' => null,
            'payload' => ['stage' => $this->stage->value, 'error' => mb_substr($exception->getMessage(), 0, 500)],
        ]);
    }
}
