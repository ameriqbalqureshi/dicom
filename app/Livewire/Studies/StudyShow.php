<?php

namespace App\Livewire\Studies;

use App\Models\DicomInstance;
use App\Models\Study;
use App\Services\Dicom\StudyDeleter;
use App\Services\Dicom\ViewerSeries;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class StudyShow extends Component
{
    public Study $study;

    public function mount(Study $study): void
    {
        $this->authorize('view', $study);

        $this->study = $study;
    }

    public function delete(StudyDeleter $deleter): void
    {
        $this->authorize('delete', $this->study);

        $deleter->delete($this->study);

        session()->flash('status', 'Study deleted.');

        $this->redirectRoute('studies.index');
    }

    public function render(): View
    {
        return view('livewire.studies.study-show', [
            'viewerSeries' => ViewerSeries::forStudy($this->study, fn (DicomInstance $instance): string => route('instances.file', $instance)),
        ])->title($this->study->patient_name ?? 'Study');
    }
}
