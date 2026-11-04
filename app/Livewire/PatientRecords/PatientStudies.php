<?php

namespace App\Livewire\PatientRecords;

use App\Models\PatientRecord;
use App\Models\Study;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

/**
 * Lists the studies linked to a patient record and lets its physician link any study they can open, or remove a link.
 * Removing a link does not touch the study.
 */
class PatientStudies extends Component
{
    /**
     * How many studies are offered at a time while linking.
     */
    private const MAX_RESULTS = 10;

    public PatientRecord $patientRecord;

    public bool $linking = false;

    public string $search = '';

    public function mount(PatientRecord $patientRecord): void
    {
        $this->authorize('view', $patientRecord);
    }

    public function startLinking(): void
    {
        $this->authorize('update', $this->patientRecord);

        $this->linking = true;
    }

    public function stopLinking(): void
    {
        $this->reset('linking', 'search');
    }

    public function link(int $studyId): void
    {
        $this->authorize('update', $this->patientRecord);

        $study = Study::query()->visibleTo(auth()->user())->findOrFail($studyId);

        $this->patientRecord->linkedStudies()->firstOrCreate(['study_id' => $study->id], [
            'study_date' => $study->study_date,
            'study_description' => $study->study_description,
        ]);
    }

    public function unlink(int $linkId): void
    {
        $this->authorize('update', $this->patientRecord);

        $this->patientRecord->linkedStudies()->findOrFail($linkId)->delete();
    }

    /**
     * The studies the physician can open that are not linked yet, most recent first.
     *
     * @return Collection<int, Study>
     */
    private function linkableStudies(): Collection
    {
        return Study::query()
            ->visibleTo(auth()->user())
            ->whereNotIn('id', $this->patientRecord->linkedStudies()->whereNotNull('study_id')->pluck('study_id'))
            ->search($this->search)
            ->orderByDesc('study_date')
            ->orderByDesc('id')
            ->limit(self::MAX_RESULTS)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.patient-records.patient-studies', [
            'linkedStudies' => $this->patientRecord->linkedStudies()->with('study')->get(),
            'linkableStudies' => $this->linking ? $this->linkableStudies() : collect(),
        ]);
    }
}
