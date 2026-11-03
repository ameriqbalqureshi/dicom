<?php

namespace App\Http\Controllers;

use App\Models\Study;
use App\Services\CommentsPdf;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class CommentsPdfController extends Controller
{
    /**
     * Show a study's comments as a PDF to a user who may open the study.
     */
    public function __invoke(Study $study, CommentsPdf $pdf): Response
    {
        Gate::authorize('view', $study);

        return $pdf->stream($study);
    }
}
