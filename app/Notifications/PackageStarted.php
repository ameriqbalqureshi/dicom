<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a user their package has started, after an administrator confirmed their payment or gave them the package.
 */
class PackageStarted extends Notification implements ShouldQueue
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
            ->subject("Your {$subscription->package->name} package has started")
            ->line($subscription->amount > 0 ? 'We have confirmed your payment.' : "You have been given the {$subscription->package->name} package.")
            ->line($subscription->ends_at ? "It runs until {$subscription->ends_at->toFormattedDateString()}." : 'It does not expire.')
            ->action('View package', route('package.show'));
    }
}
