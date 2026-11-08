<?php

namespace App\Notifications;

use App\Models\Commission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a marketing officer they earned commission on a client's payment.
 */
class CommissionEarned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Commission $commission)
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
        $amount = number_format($this->commission->amount).' '.config('billing.currency');

        return (new MailMessage)
            ->subject("You earned {$amount} in commission")
            ->line("{$this->commission->client_name} made a payment, and you earned {$amount}.")
            ->line('It can be paid to you from '.$this->commission->available_at->toFormattedDateString().'.')
            ->action('See your earnings', route('marketing.dashboard'));
    }
}
