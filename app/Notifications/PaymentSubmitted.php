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

        return (new MailMessage)
            ->subject("Payment to confirm: {$subscription->user->name}")
            ->line("{$subscription->user->name} ({$subscription->user->email}) paid for the {$subscription->package->name} package.")
            ->line('Amount: '.number_format($subscription->amount).' '.config('billing.currency')." into {$subscription->payment_method}")
            ->line('Reference: '.($subscription->payment_reference ?? 'none given'))
            ->line($subscription->receipt_path === null ? 'No receipt was attached.' : 'A receipt is attached on the Payments page.')
            ->action('Review payment', route('payments.index'));
    }
}
