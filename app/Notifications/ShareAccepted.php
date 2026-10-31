<?php

namespace App\Notifications;

use App\Models\Study;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a patient that the physician they shared their study with accepted it.
 */
class ShareAccepted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Study $study, public User $physician)
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
            ->subject("{$this->physician->name} accepted your study")
            ->line("{$this->physician->name} accepted your study and can now review it.")
            ->action('Open study', route('studies.show', $this->study));
    }
}
