<?php

namespace App\Notifications;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the owner when the superadmin suspends a listing: the reason and how to reach support.
 */
class ListingSuspended extends Notification implements ShouldQueue
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
        $message = (new MailMessage)
            ->subject(__('Your listing “:title” has been suspended', ['title' => $this->listing->title]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('We have withdrawn “:title” from the marketplace.', ['title' => $this->listing->title]))
            ->line(__('Reason: :reason', ['reason' => $this->listing->suspension_reason]))
            ->line(__('Your data is intact. If you think this is a mistake or want to fix the issue, contact us and we will review it.'));

        $supportEmail = config('avytra.support.email');

        if (is_string($supportEmail) && $supportEmail !== '') {
            $message->action(__('Contact AVYTRA'), 'mailto:'.$supportEmail);
        }

        return $message->salutation(__('The AVYTRA team'));
    }
}
