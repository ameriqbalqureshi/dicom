<?php

namespace App\Notifications;

use App\Models\MarketingApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells administrators someone applied to become a marketing officer.
 */
class MarketingApplicationSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public MarketingApplication $application)
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
            ->subject('New marketing officer application')
            ->line("{$this->application->name} ({$this->application->email}) applied to become a marketing officer.")
            ->action('Review the application', route('marketing.index'));
    }
}
