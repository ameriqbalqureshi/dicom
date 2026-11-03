<?php

namespace App\Services;

use App\Models\Study;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders a study's comments as a printable PDF with the patient details, separate from its reports.
 */
class CommentsPdf
{
    /**
     * Show the PDF in the browser.
     */
    public function stream(Study $study): Response
    {
        $pdf = Pdf::loadView('comments.pdf', [
            'study' => $study,
            'comments' => $study->comments()->with('author')->get(),
        ])->setPaper('a4');

        $patient = Str::slug($study->patient_name ?? 'patient');

        return $pdf->stream("comments-{$study->id}-{$patient}.pdf")->withHeaders([
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
