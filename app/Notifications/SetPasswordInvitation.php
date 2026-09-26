<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Your account is ready": sent when the superadmin creates an account for somebody and
 * chooses to let them set their own password (docs/03). The link is Fortify's reset form.
 */
class SetPasswordInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(public string $token) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = url(route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()], false));
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject(__('Your AVYTRA account is ready'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('We have created an account for you on AVYTRA so your business can be published. To sign in, choose a password with the button below.'))
            ->action(__('Set my password'), $url)
            ->line(__('The link works for :minutes minutes. If it has expired, use “Forgot your password?” on the sign-in page with this email address.', ['minutes' => $minutes]))
            ->line(__('If you were not expecting this email, you can ignore it.'))
            ->salutation(__('The AVYTRA team'));
    }
}
