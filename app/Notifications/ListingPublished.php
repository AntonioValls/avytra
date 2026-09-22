<?php

namespace App\Notifications;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the owner the first time a listing is published: confirmation plus an
 * explanation of the availability system (docs/13-freshness-and-notifications.md).
 */
class ListingPublished extends Notification implements ShouldQueue
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
        $days = (int) config('avytra.freshness.confirmation_period_days');
        $firstReminder = (int) config('avytra.freshness.first_reminder_days');

        return (new MailMessage)
            ->subject(__('Your listing “:title” is now published', ['title' => $this->listing->title]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('“:title” is now visible to buyers on AVYTRA.', ['title' => $this->listing->title]))
            ->line(__('To keep the marketplace reliable we ask every seller to confirm, every :days days, that the business is still available. We will remind you by email :reminder days after your last confirmation, and if we hear nothing the listing is paused (never deleted). One click brings it back.', ['days' => $days, 'reminder' => $firstReminder]))
            ->action(__('Go to my listings'), route('listings.index'))
            ->line(__('Thank you for publishing with AVYTRA.'))
            ->salutation(__('The AVYTRA team'));
    }
}
