<?php

namespace App\Services;

use App\Models\PatientRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders a patient record as a printable PDF.
 */
class PatientRecordPdf
{
    /**
     * Show the PDF in the browser, with or without the physician's notes, which they may not want to hand over.
     */
    public function stream(PatientRecord $patientRecord, bool $includeNotes = true): Response
    {
        $pdf = Pdf::loadView('patient-records.pdf', [
            'record' => $patientRecord,
            'owner' => $patientRecord->owner,
            'linkedStudies' => $patientRecord->linkedStudies()->get(),
            'notes' => $includeNotes ? $patientRecord->notes()->get() : collect(),
        ])->setPaper('a4');

        $patient = Str::slug($patientRecord->name);

        return $pdf->stream("patient-record-{$patientRecord->id}-{$patient}.pdf")->withHeaders([
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
