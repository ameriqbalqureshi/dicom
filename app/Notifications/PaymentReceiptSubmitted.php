<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells administrators a receipt arrived through the public form. Anyone can fill in the form, so the email repeats
 * nothing they typed; administrators see it on the Payments page.
 */
class PaymentReceiptSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

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
            ->subject('Payment receipt received')
            ->line('A payment receipt was sent through the website.')
            ->action('Review receipt', route('payments.index'));
    }
}
