<?php

namespace App\Notifications;

use App\Enums\ListingEventType;
use App\Models\Listing;
use App\Models\User;
use App\Support\Listings\ConfirmationLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Throwable;

/**
 * Sent to the owner when the freshness command pauses a listing: data intact, one click
 * brings it back (docs/13). The link leads to the same authenticated confirmation page.
 */
class ListingExpired extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(public Listing $listing) {}

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

        return (new MailMessage)
            ->subject(__('We have paused “:title”', ['title' => $title]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('We could not confirm that “:title” is still available, so it no longer appears in search results.', ['title' => $title]))
            ->line(__('Your data is intact. If the business is still for sale, reactivate the listing with one click and it will be visible again immediately.'))
            ->action(__('Reactivate the listing'), ConfirmationLink::for($this->listing))
            ->line(__('If it was sold or you no longer want to offer it, you can mark it as sold or archive it from your panel.'))
            ->salutation(__('The AVYTRA team'));
    }

    public function failed(Throwable $exception): void
    {
        $this->listing->events()->create([
            'type' => ListingEventType::ReminderFailed,
            'actor_user_id' => null,
            'on_behalf_of_user_id' => null,
            'payload' => ['stage' => 'expired', 'error' => mb_substr($exception->getMessage(), 0, 500)],
        ]);
    }
}
