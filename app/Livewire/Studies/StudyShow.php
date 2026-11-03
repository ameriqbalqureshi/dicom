<?php

namespace App\Livewire\Studies;

use App\Models\DicomInstance;
use App\Models\Study;
use App\Services\Billing\QuotaExceededException;
use App\Services\Dicom\StudyDeleter;
use App\Services\Dicom\ViewerSeries;
use App\Services\StudyHandover;
use App\Services\StudySharing;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Component;

class StudyShow extends Component
{
    public Study $study;

    public function mount(Study $study): void
    {
        $this->authorize('view', $study);

        $this->study = $study;
    }

    /**
     * Delete the study. When its owner deletes it, the physicians who can open it may keep it instead; an administrator
     * deleting someone else's study removes it for everyone at once.
     */
    public function delete(StudyDeleter $deleter, StudyHandover $handover): void
    {
        $this->authorize('delete', $this->study);

        if (! $this->study->uploader()->is(auth()->user())) {
            $deleter->delete($this->study);
            session()->flash('status', 'Study deleted.');
        } elseif ($handover->release($this->study)) {
            session()->flash('status', 'Study removed. The physicians it was shared with have '.config('studies.handover_days').' days to keep their own copy; otherwise it is deleted.');
        } else {
            session()->flash('status', 'Study deleted.');
        }

        $this->redirectRoute('studies.index');
    }

    public function keep(StudyHandover $handover): void
    {
        $this->authorize('keep', $this->study);

        try {
            $handover->keep($this->study, auth()->user());
        } catch (InvalidArgumentException|QuotaExceededException $exception) {
            $this->addError('keep', $exception->getMessage());

            return;
        }

        session()->flash('status', 'The study is now yours. It counts towards your package like a study you uploaded.');

        $this->redirectRoute('studies.show', $this->study);
    }

    public function removeFromList(StudySharing $sharing): void
    {
        $this->authorize('removeFromList', $this->study);

        $sharing->removeFromList($this->study, auth()->user());

        session()->flash('status', 'Study removed from your list.');

        $this->redirectRoute('studies.index');
    }

    public function render(): View
    {
        return view('livewire.studies.study-show', [
            'viewerSeries' => ViewerSeries::forStudy($this->study, fn (DicomInstance $instance): string => route('instances.file', $instance)),
        ])->title($this->study->patient_name ?? 'Study');
    }
}
