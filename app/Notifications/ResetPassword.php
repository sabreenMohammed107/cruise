<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Reset your password" link for a website customer (PasswordResetController). Queued, so the form answers at
 * once; written in the language the visitor was using (words in lang/{en,ar}/emails.php).
 */
class ResetPassword extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token)
    {
        // The queue worker runs in the default locale, so keep the visitor's
        $this->locale(app()->getLocale());
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);

        return (new MailMessage)
            ->subject(__('emails.reset_subject'))
            ->view('mail.reset-password', [
                'url' => $url,
                'name' => $notifiable->first_name ?: $notifiable->name,
                'minutes' => (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
            ]);
    }
}
