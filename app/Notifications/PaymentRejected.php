<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a user an administrator could not confirm the payment for their package, and why.
 */
class PaymentRejected extends Notification implements ShouldQueue
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
            ->subject('We could not confirm your payment')
            ->line("We could not confirm your payment for the {$subscription->package->name} package".($subscription->payment_reference ? " (reference {$subscription->payment_reference})." : '.'))
            ->line("Reason: {$subscription->rejection_reason}")
            ->line('If you have paid, please submit the purchase again with the correct details, or reply to this email.')
            ->action('View package', route('package.show'));
    }
}
