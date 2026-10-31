<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Welcomes a user whose account an administrator created. The password is not sent; they can ask the administrator for
 * it or set their own with the reset link.
 */
class AccountCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $admin)
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
            ->subject('Your '.config('app.name').' account')
            ->line("{$this->admin->name} created a {$notifiable->role->label()} account for you with this email address.")
            ->action('Sign in', route('login'))
            ->line('To choose your own password, use "Forgot your password?" on the sign-in page.');
    }
}
