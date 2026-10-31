<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Asks administrators to verify a physician who signed up.
 */
class PhysicianSignedUp extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $physician)
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
        $physician = $this->physician;

        return (new MailMessage)
            ->subject("Physician to verify: {$physician->name}")
            ->line("{$physician->name} ({$physician->email}) signed up as a physician.")
            ->line('Registration number: '.($physician->registration_number ?? 'not given'))
            ->line('Qualifications: '.($physician->qualifications ?? 'not given'))
            ->line('Check their registration before verifying them. Until then they cannot receive shared studies or sign reports.')
            ->action('Review users', route('users.index'));
    }
}
