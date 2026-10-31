<?php

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the owner of a study that a physician has signed a report on it.
 */
class ReportFinalized extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Report $report)
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
        $study = $this->report->study;

        return (new MailMessage)
            ->subject("Report ready: {$study->patient_name}")
            ->line("{$this->report->authorName()} has signed a report on ".($study->study_description ?: 'your study').'.')
            ->action('Read report', route('studies.show', $study));
    }
}
