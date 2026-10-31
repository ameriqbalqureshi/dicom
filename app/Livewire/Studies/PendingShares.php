<?php

namespace App\Livewire\Studies;

use App\Models\StudyShare;
use App\Services\StudySharing;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Lists the studies patients have shared with the physician that are awaiting acceptance, and lets them accept or
 * decline each one.
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

    public function render(): View
    {
        return view('livewire.studies.pending-shares', [
            'shares' => auth()->user()->receivedShares()->pending()
                ->with('sharer:id,name,email', 'study:id,patient_name,study_date,study_description')
                ->latest()
                ->get(),
        ]);
    }
}
