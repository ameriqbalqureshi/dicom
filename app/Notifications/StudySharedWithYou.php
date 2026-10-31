<?php

namespace App\Notifications;

use App\Models\Study;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a physician a study was shared with them, or, when a patient shared it, that they can accept it.
 */
class StudySharedWithYou extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Study $study, public User $sharer, public bool $awaitingAcceptance)
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
        $description = $this->study->study_description ?: 'a study';

        if ($this->awaitingAcceptance) {
            return (new MailMessage)
                ->subject("{$this->sharer->name} wants to share a study with you")
                ->line("{$this->sharer->name} would like you to review {$description}.")
                ->line('You can open it once you accept the request.')
                ->action('Review request', route('shares.pending'));
        }

        return (new MailMessage)
            ->subject("{$this->sharer->name} shared a study with you")
            ->line("{$this->sharer->name} shared {$description} with you.")
            ->action('Open study', route('studies.show', $this->study));
    }
}
