<?php

namespace App\Notifications;

use App\Models\ContactRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Throwable;

/**
 * Relays a message from an interested buyer to the seller's inbox (docs/12, ADR-019).
 * Sent on demand to the listing contact email or the owner's account email, with the
 * sender as Reply-To so the seller answers from their own mail client. The sender's
 * address is the only thing revealed, and only to the seller.
 */
class ContactRequestReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(public ContactRequest $request) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $listing = $this->request->listing;
        $title = (string) $listing->title;

        $mail = (new MailMessage)
            ->subject(__('New message about “:title”', ['title' => $title]))
            ->replyTo($this->request->sender_email, $this->request->sender_name)
            ->greeting(__('Hello :name,', ['name' => $listing->contactInboxName()]))
            ->line(__(':name has written to you from your listing “:title” on AVYTRA.', ['name' => $this->request->sender_name, 'title' => $title]))
            ->line(__('Their message:'))
            ->line($this->request->message)
            ->line(__('Email: :email', ['email' => $this->request->sender_email]));

        if ($this->request->sender_phone !== null) {
            $mail->line(__('Phone: :phone', ['phone' => $this->request->sender_phone]));
        }

        return $mail
            ->line(__('Reply to this email to answer directly. Your address is only shared with them if you do.'))
            ->action(__('See my messages'), route('panel.messages.index'))
            ->salutation(__('The AVYTRA team'));
    }

    public function failed(Throwable $exception): void
    {
        $this->request->forceFill([
            'delivery_failed_at' => now(),
            'delivery_error' => mb_substr($exception->getMessage(), 0, 500),
        ])->save();
    }
}
