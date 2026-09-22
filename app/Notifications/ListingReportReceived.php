<?php

namespace App\Notifications;

use App\Models\ListingReport;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to every superadmin when a visitor reports a listing.
 */
class ListingReportReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(public ListingReport $report) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $listing = $this->report->listing;

        $message = (new MailMessage)
            ->subject(__('New report on “:title”', ['title' => $listing->title]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('Somebody reported the listing “:title”.', ['title' => $listing->title]))
            ->line(__('Reason: :reason', ['reason' => $this->report->reason->label()]));

        if ($this->report->message !== null) {
            $message->line(__('Message: :message', ['message' => $this->report->message]));
        }

        return $message
            ->action(__('Open the reports inbox'), route('admin.reports.index'))
            ->salutation(__('The AVYTRA team'));
    }
}
