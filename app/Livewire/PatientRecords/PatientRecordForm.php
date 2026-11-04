<?php

namespace App\Livewire\PatientRecords;

use App\Enums\Quota;
use App\Enums\RiskFactor;
use App\Enums\Sex;
use App\Models\PatientRecord;
use App\Services\Billing\PackageLimits;
use App\Services\Billing\QuotaExceededException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Creates a patient record, or edits one the physician already has. Only the patient's name is required.
 */
class PatientRecordForm extends Component
{
    /**
     * The record being edited, or null for a new one.
     */
    public ?PatientRecord $patientRecord = null;

    public string $name = '';

    public string $medicalRecordNumber = '';

    public string $dateOfBirth = '';

    public string $sex = '';

    public string $phone = '';

    /** @var list<string> */
    public array $riskFactors = [];

    /**
     * The text of each clinical section, by section name.
     *
     * @var array<string, string>
     */
    public array $clinical = [];

    public function mount(?PatientRecord $patientRecord = null): void
    {
        if ($patientRecord?->exists) {
            $this->authorize('update', $patientRecord);

            $this->patientRecord = $patientRecord;
            $this->name = $patientRecord->name;
            $this->medicalRecordNumber = $patientRecord->medical_record_number ?? '';
            $this->dateOfBirth = $patientRecord->date_of_birth?->format('Y-m-d') ?? '';
            $this->sex = $patientRecord->sex->value ?? '';
            $this->phone = $patientRecord->phone ?? '';
            $this->riskFactors = $patientRecord->risk_factors ?? [];
        } else {
            $this->patientRecord = null;
            $this->authorize('create', PatientRecord::class);
        }

        foreach (array_keys(PatientRecord::CLINICAL_SECTIONS) as $section) {
            $this->clinical[$section] = $this->patientRecord?->{$section} ?? '';
        }
    }

    public function save(PackageLimits $limits): void
    {
        $this->patientRecord === null
            ? $this->authorize('create', PatientRecord::class)
            : $this->authorize('update', $this->patientRecord);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'medicalRecordNumber' => ['nullable', 'string', 'max:100'],
            'dateOfBirth' => ['nullable', 'date', 'before_or_equal:today'],
            'sex' => ['nullable', Rule::enum(Sex::class)],
            'phone' => ['nullable', 'string', 'max:30'],
            'riskFactors' => ['array'],
            'riskFactors.*' => [Rule::enum(RiskFactor::class)],
            'clinical' => ['array'],
            'clinical.*' => ['nullable', 'string', 'max:10000'],
        ], [], [
            'clinical.*' => 'clinical details',
        ]);

        $attributes = [
            'name' => trim($validated['name']),
            'medical_record_number' => $this->nullIfBlank($validated['medicalRecordNumber']),
            'date_of_birth' => $this->nullIfBlank($validated['dateOfBirth']),
            'sex' => $this->nullIfBlank($validated['sex']),
            'phone' => $this->nullIfBlank($validated['phone']),
            'risk_factors' => array_values($validated['riskFactors']),
        ];

        foreach (array_keys(PatientRecord::CLINICAL_SECTIONS) as $section) {
            $attributes[$section] = $this->nullIfBlank($validated['clinical'][$section] ?? null);
        }

        if ($this->patientRecord !== null) {
            $this->patientRecord->update($attributes);
            $record = $this->patientRecord;
        } else {
            try {
                $record = DB::transaction(function () use ($limits, $attributes): PatientRecord {
                    $limits->spend(auth()->user(), Quota::PatientRecords);

                    return auth()->user()->patientRecords()->create($attributes);
                });
            } catch (QuotaExceededException $exception) {
                $this->addError('limit', $exception->getMessage());

                return;
            }
        }

        session()->flash('status', 'Patient record saved.');
        $this->redirectRoute('patient-records.show', $record);
    }

    private function nullIfBlank(?string $value): ?string
    {
        return filled($value) ? trim($value) : null;
    }

    public function render(): View
    {
        return view('livewire.patient-records.patient-record-form', [
            'sexes' => Sex::cases(),
            'riskFactorOptions' => RiskFactor::cases(),
            'sections' => PatientRecord::CLINICAL_SECTIONS,
        ])->title($this->patientRecord === null ? 'New patient' : "Edit {$this->patientRecord->name}");
    }
}
