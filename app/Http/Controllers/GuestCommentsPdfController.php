<?php

namespace App\Http\Controllers;

use App\Models\GuestLink;
use App\Services\CommentsPdf;
use Symfony\Component\HttpFoundation\Response;

class GuestCommentsPdfController extends Controller
{
    /**
     * Show a study's comments as a PDF to someone holding a guest link to it.
     */
    public function __invoke(string $token, CommentsPdf $pdf): Response
    {
        $link = GuestLink::findByToken($token);

        abort_unless($link?->isUsable() && $link->isUnlocked(), 404);

        return $pdf->stream($link->study);
    }
}
