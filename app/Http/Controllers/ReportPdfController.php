<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Services\ReportPdf;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ReportPdfController extends Controller
{
    /**
     * Show a report as a PDF to a user who may read it.
     */
    public function __invoke(Report $report, ReportPdf $pdf): Response
    {
        Gate::authorize('view', $report);

        return $pdf->stream($report);
    }
}
