<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells an applicant their application to become a marketing officer was not accepted.
 */
class MarketingApplicationRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?string $reason = null)
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
        $message = (new MailMessage)
            ->subject('Your marketing officer application')
            ->line('Thank you for applying. We are not able to accept your application at this time.');

        return $this->reason === null ? $message : $message->line("Reason: {$this->reason}");
    }
}
