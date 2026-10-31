<?php

namespace App\Services;

use App\Models\Report;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders a report as a printable PDF with the patient details and the author's signature block.
 */
class ReportPdf
{
    /**
     * Show the PDF in the browser. A final report is signed with the credentials recorded when it was finalized; a
     * draft shows the author's current ones and is watermarked.
     */
    public function stream(Report $report): Response
    {
        $report->loadMissing(['study', 'author', 'addenda']);

        $pdf = Pdf::loadView('reports.pdf', [
            'report' => $report,
            'study' => $report->study,
            'qualifications' => $report->isFinal() ? $report->author_qualifications : $report->author?->qualifications,
            'registrationNumber' => $report->isFinal() ? $report->author_registration_number : $report->author?->registration_number,
            'signature' => $report->author?->signatureDataUri(),
        ])->setPaper('a4');

        return $pdf->stream($this->filename($report))->withHeaders([
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function filename(Report $report): string
    {
        $patient = Str::slug($report->study->patient_name ?? 'patient');

        return "report-{$report->id}-{$patient}".($report->isFinal() ? '' : '-draft').'.pdf';
    }
}
