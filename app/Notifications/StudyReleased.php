<?php

namespace App\Notifications;

use App\Models\Study;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a physician that the owner of a study they can open has deleted it, or that a patient's study has expired, and
 * that they can keep it as their own until the handover ends.
 */
class StudyReleased extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Study $study, public ?string $formerOwnerName, public bool $expired, public CarbonInterface $handoverEndsAt)
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
        $formerOwner = $this->formerOwnerName ?? 'Its owner';
        $deadline = $this->handoverEndsAt->toFormattedDateString();

        return (new MailMessage)
            ->subject($this->expired ? 'A patient study shared with you has expired' : "{$formerOwner} has deleted a study shared with you")
            ->line($this->expired
                ? "{$formerOwner}'s copy of {$description}, which you can open, has reached the end of its storage period."
                : "{$formerOwner} has deleted their copy of {$description}, which you can open.")
            ->line("You can keep it as your own study until {$deadline}. It then counts towards your package. If nobody keeps it, it will be deleted on that date.")
            ->action('Keep or remove', route('shares.pending'));
    }
}
