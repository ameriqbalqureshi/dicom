<?php

namespace App\Livewire\PatientRecords;

use App\Enums\Quota;
use App\Models\PatientRecord;
use App\Services\Billing\PackageLimits;
use App\Services\Billing\QuotaExceededException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Lists a physician's own patient records, most recently changed first.
 */
#[Title('Patients')]
class PatientRecordIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', PatientRecord::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(PackageLimits $limits): View
    {
        return view('livewire.patient-records.patient-record-index', [
            'records' => auth()->user()->patientRecords()
                ->search($this->search)
                ->withCount(['notes', 'linkedStudies'])
                ->latest('updated_at')
                ->latest('id')
                ->paginate(15),
            'limitMessage' => $this->limitMessage($limits),
        ]);
    }

    /**
     * Why the physician cannot add another record, if they cannot. They can still open and change the ones they have.
     */
    private function limitMessage(PackageLimits $limits): ?string
    {
        try {
            $limits->ensure(auth()->user(), Quota::PatientRecords);
        } catch (QuotaExceededException $exception) {
            return $exception->getMessage();
        }

        return null;
    }
}
