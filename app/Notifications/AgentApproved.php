<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sign-in details for an approved agent (Admin\AgentRequestController::approve / resend): the email and a temporary
 * password. Agent accounts are always new accounts (App\Services\AgentAccounts).
 *
 * Not queued on purpose: the queue stores jobs in the database (QUEUE_CONNECTION=database), and the password must
 * not sit there in plain text. The controller sends it at once and tells the admin if sending failed.
 */
class AgentApproved extends Notification
{
    use Queueable;

    public function __construct(public string $company, #[\SensitiveParameter] public string $password) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('emails.agent_subject'))
            ->view('mail.agent-approved', [
                'name' => $notifiable->first_name ?: $notifiable->name,
                'company' => $this->company,
                'email' => $notifiable->email,
                'password' => $this->password,
                'url' => route('login'),
            ]);
    }
}
