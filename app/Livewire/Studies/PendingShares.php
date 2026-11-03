<?php

namespace App\Livewire\Studies;

use App\Models\Study;
use App\Models\StudyShare;
use App\Services\Billing\QuotaExceededException;
use App\Services\StudyHandover;
use App\Services\StudySharing;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Lists the shares awaiting the physician's acceptance (from senders they have not allowed yet), and lets them accept,
 * decline, or block the sender. Also lists studies their owner gave up, which the physician can keep or remove from
 * their list.
 */
#[Title('Requests')]
class PendingShares extends Component
{
    public ?string $status = null;

    public function accept(int $shareId, StudySharing $sharing): void
    {
        $share = StudyShare::findOrFail($shareId);
        $this->authorize('respond', $share);

        $sharing->accept($share);

        $this->status = "Accepted the study from {$share->sharer->name}. It is now in your study list.";
    }

    public function decline(int $shareId, StudySharing $sharing): void
    {
        $share = StudyShare::findOrFail($shareId);
        $this->authorize('respond', $share);

        $sharing->revoke($share);

        $this->status = "Declined the study from {$share->sharer->name}.";
    }

    public function blockSender(int $shareId, StudySharing $sharing): void
    {
        $share = StudyShare::findOrFail($shareId);
        $this->authorize('respond', $share);

        $sharing->block(auth()->user(), $share->sharer);

        $this->status = "Blocked {$share->sharer->name}. Their shares will not reach you, and the studies they shared with you were removed.";
    }

    public function keep(int $studyId, StudyHandover $handover): void
    {
        $study = Study::findOrFail($studyId);
        $this->authorize('keep', $study);

        try {
            $handover->keep($study, auth()->user());
        } catch (InvalidArgumentException|QuotaExceededException $exception) {
            $this->addError("handover.{$study->id}", $exception->getMessage());

            return;
        }

        $this->status = 'The study is now yours. It counts towards your package like a study you uploaded.';
    }

    public function remove(int $studyId, StudySharing $sharing): void
    {
        $study = Study::findOrFail($studyId);
        $this->authorize('removeFromList', $study);

        $sharing->removeFromList($study, auth()->user());

        $this->status = 'Removed the study from your list.';
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.studies.pending-shares', [
            'shares' => $user->receivedShares()->pending()
                ->with('sharer:id,name,email', 'study:id,patient_name,study_date,study_description')
                ->latest()
                ->get(),
            'handovers' => Study::query()->sharedWith($user)->awaitingHandover()
                ->with('formerOwner:id,name')
                ->orderBy('handover_ends_at')
                ->get(),
        ]);
    }
}
