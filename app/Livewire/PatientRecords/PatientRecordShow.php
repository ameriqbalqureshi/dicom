<?php

namespace App\Livewire\PatientRecords;

use App\Models\PatientRecord;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * A patient record as its physician reviews it: the patient's details and clinical sections, with the linked studies
 * and notes in their own components.
 */
class PatientRecordShow extends Component
{
    public PatientRecord $patientRecord;

    public function mount(PatientRecord $patientRecord): void
    {
        $this->authorize('view', $patientRecord);
    }

    /**
     * Delete the record with its notes. The studies linked to it are not touched.
     */
    public function delete(): void
    {
        $this->authorize('delete', $this->patientRecord);

        $name = $this->patientRecord->name;
        $this->patientRecord->delete();

        session()->flash('status', "The record of {$name} was deleted.");
        $this->redirectRoute('patient-records.index');
    }

    public function render(): View
    {
        return view('livewire.patient-records.patient-record-show', [
            'sections' => PatientRecord::CLINICAL_SECTIONS,
        ])->title($this->patientRecord->name);
    }
}
