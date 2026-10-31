<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Reminds a user that their package is about to end.
 */
class PackageEndingSoon extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Subscription $subscription)
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
        $subscription = $this->subscription;

        return (new MailMessage)
            ->subject("Your {$subscription->package->name} package ends {$subscription->ends_at->toFormattedDateString()}")
            ->line("Your {$subscription->package->name} package ends on {$subscription->ends_at->toDayDateTimeString()}.")
            ->line('After that you can still open your studies, but you will need a package to upload, share or sign reports.')
            ->action('Choose a package', route('package.show'));
    }
}
