<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Forwards a message sent through the public contact form to an administrator.
 */
class ContactMessageReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $name,
        public string $email,
        public ?string $organisation,
        public string $topic,
        public string $message,
    ) {}

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
            ->subject("Contact form: {$this->topic}")
            ->replyTo($this->email, $this->name)
            ->line("From: {$this->name} ({$this->email})")
            ->line('Organisation: '.($this->organisation ?? 'not given'))
            ->line("Topic: {$this->topic}")
            ->line('Message:')
            ->line($this->message)
            ->line('Reply to this email to answer them.');
    }
}
