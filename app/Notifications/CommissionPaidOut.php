<?php

namespace App\Notifications;

use App\Models\CommissionPayout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a marketing officer their commission was paid, with a link to the statement.
 */
class CommissionPaidOut extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public CommissionPayout $payout)
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
        $amount = number_format($this->payout->amount).' '.config('billing.currency');

        return (new MailMessage)
            ->subject("We paid you {$amount} in commission")
            ->line("We sent {$amount} through {$this->payout->payment_method}.")
            ->line('Reference: '.($this->payout->payment_reference ?? 'none given'))
            ->action('View the statement', route('marketing.statement', $this->payout));
    }
}
