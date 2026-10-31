<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Asks administrators to check a payment a user reported for a package.
 */
class PaymentSubmitted extends Notification implements ShouldQueue
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
        $method = config("billing.payment_methods.{$subscription->payment_method}", $subscription->payment_method);

        return (new MailMessage)
            ->subject("Payment to confirm: {$subscription->user->name}")
            ->line("{$subscription->user->name} ({$subscription->user->email}) paid for the {$subscription->package->name} package.")
            ->line('Amount: '.number_format($subscription->amount).' '.config('billing.currency')." by {$method}")
            ->line("Reference: {$subscription->payment_reference}")
            ->action('Review payment', route('payments.index'));
    }
}
