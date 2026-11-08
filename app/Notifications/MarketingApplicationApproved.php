<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Password;

/**
 * Welcomes an approved marketing officer and lets them set a password. The link is made when the email is sent, because
 * password links only last a limited time and queued emails can wait.
 */
class MarketingApplicationApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome: you are now a marketing officer')
            ->line('Your application was approved. Set a password to see your referral link and start earning.')
            ->action('Set your password', route('password.reset', [
                'token' => Password::broker()->createToken($notifiable),
                'email' => $notifiable->getEmailForPasswordReset(),
            ]))
            ->line('If this link has expired, use "Forgot password" on the sign-in page.');
    }
}
