<?php

namespace App\Livewire\Studies;

use App\Models\Report;
use App\Models\Study;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Lists a study's final reports, and lets physicians who can open the study write their own: a draft only they can see
 * and edit, which they finalize to lock it and publish it to everyone with access, then correct with addenda.
 */
class StudyReports extends Component
{
    public Study $study;

    /**
     * Whether the draft form is open, and the draft being edited (null for a new report).
     */
    public bool $editing = false;

    public ?int $draftId = null;

    public string $indication = '';

    public string $findings = '';

    public string $impression = '';

    /**
     * The final report an addendum is being written for.
     */
    public ?int $addendumReportId = null;

    public string $addendum = '';

    public ?string $status = null;

    public function mount(Study $study): void
    {
        $this->authorize('view', $study);

        $this->study = $study;
    }

    public function startReport(): void
    {
        $this->authorize('create', [Report::class, $this->study]);

        $this->resetDraft();
        $this->editing = true;
    }

    public function editDraft(int $reportId): void
    {
        $report = $this->findReport($reportId);
        $this->authorize('update', $report);

        $this->resetDraft();
        $this->editing = true;
        $this->draftId = $report->id;
        $this->indication = $report->indication ?? '';
        $this->findings = $report->findings;
        $this->impression = $report->impression;
    }

    public function cancelDraft(): void
    {
        $this->resetDraft();
    }

    public function saveDraft(): void
    {
        $this->saveValidatedDraft();

        $this->resetDraft();
        $this->status = 'Draft saved. Only you can see it until you finalize it.';
    }

    /**
     * Save the form and finalize the report in one step.
     */
    public function saveAndFinalize(): void
    {
        $report = $this->saveValidatedDraft();
        $report->finalize();

        $this->resetDraft();
        $this->status = 'Report finalized. Everyone who can open this study can now read it.';
    }

    public function finalize(int $reportId): void
    {
        $report = $this->findReport($reportId);
        $this->authorize('update', $report);

        $report->finalize();

        $this->status = 'Report finalized. Everyone who can open this study can now read it.';
    }

    public function discardDraft(int $reportId): void
    {
        $report = $this->findReport($reportId);
        $this->authorize('delete', $report);

        $report->delete();

        if ($this->draftId === $report->id) {
            $this->resetDraft();
        }
        $this->status = 'Draft discarded.';
    }

    public function startAddendum(int $reportId): void
    {
        $this->authorize('addAddendum', $this->findReport($reportId));

        $this->addendumReportId = $reportId;
        $this->reset('addendum');
        $this->resetValidation('addendum');
    }

    public function cancelAddendum(): void
    {
        $this->reset('addendumReportId', 'addendum');
        $this->resetValidation('addendum');
    }

    public function saveAddendum(): void
    {
        $report = $this->findReport((int) $this->addendumReportId);
        $this->authorize('addAddendum', $report);

        $validated = $this->validate(['addendum' => ['required', 'string', 'max:10000']]);

        $report->addenda()->create(['body' => $validated['addendum']]);

        $this->cancelAddendum();
        $this->status = 'Addendum added.';
    }

    /**
     * Validate the form and create or update the draft, which must still be editable.
     */
    private function saveValidatedDraft(): Report
    {
        if ($this->draftId === null) {
            $this->authorize('create', [Report::class, $this->study]);
            $report = new Report(['study_id' => $this->study->id, 'author_id' => auth()->id()]);
        } else {
            $report = $this->findReport($this->draftId);
            $this->authorize('update', $report);
        }

        $report->fill($this->validate([
            'indication' => ['nullable', 'string', 'max:2000'],
            'findings' => ['required', 'string', 'max:20000'],
            'impression' => ['required', 'string', 'max:5000'],
        ]))->save();

        return $report;
    }

    private function findReport(int $reportId): Report
    {
        return $this->study->reports()->readableBy(auth()->user())->findOrFail($reportId)->setRelation('study', $this->study);
    }

    private function resetDraft(): void
    {
        $this->reset('editing', 'draftId', 'indication', 'findings', 'impression');
        $this->resetValidation(['indication', 'findings', 'impression']);
    }

    public function render(): View
    {
        $user = auth()->user();

        $reports = $this->study->reports()
            ->readableBy($user)
            ->with(['author', 'addenda'])
            ->orderByRaw('finalized_at is null')
            ->oldest('finalized_at')
            ->oldest()
            ->get()
            ->each(fn (Report $report) => $report->setRelation('study', $this->study));

        return view('livewire.studies.study-reports', [
            'reports' => $reports,
            'canWrite' => $user->can('create', [Report::class, $this->study]),
            'credentialsMissing' => $user->isPhysician() && blank($user->registration_number),
        ]);
    }
}
