<?php

namespace App\Http\Controllers;

use App\Models\GuestLink;
use App\Models\Report;
use App\Services\ReportPdf;
use Symfony\Component\HttpFoundation\Response;

class GuestReportPdfController extends Controller
{
    /**
     * Show a final report as a PDF to someone holding a guest link to its study.
     */
    public function __invoke(string $token, Report $report, ReportPdf $pdf): Response
    {
        $link = GuestLink::findByToken($token);

        abort_unless($link?->isUsable() && $link->isUnlocked() && $report->isFinal() && $report->study_id === $link->study_id, 404);

        return $pdf->stream($report);
    }
}
