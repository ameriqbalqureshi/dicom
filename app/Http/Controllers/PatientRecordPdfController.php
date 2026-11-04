<?php

namespace App\Http\Controllers;

use App\Models\PatientRecord;
use App\Services\PatientRecordPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class PatientRecordPdfController extends Controller
{
    /**
     * Show a patient record as a PDF to the physician it belongs to.
     */
    public function __invoke(Request $request, PatientRecord $patientRecord, PatientRecordPdf $pdf): Response
    {
        Gate::authorize('view', $patientRecord);

        return $pdf->stream($patientRecord, $request->boolean('notes', true));
    }
}
