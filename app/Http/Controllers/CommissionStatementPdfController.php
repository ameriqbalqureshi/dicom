<?php

namespace App\Http\Controllers;

use App\Models\CommissionPayout;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class CommissionStatementPdfController extends Controller
{
    /**
     * Show the statement for a commission payout as a PDF, listing what it paid for.
     */
    public function __invoke(CommissionPayout $commissionPayout): Response
    {
        Gate::authorize('view', $commissionPayout);

        return Pdf::loadView('marketing.statement-pdf', [
            'payout' => $commissionPayout,
            'lines' => $commissionPayout->commissions()->oldest()->get(),
            'currency' => config('billing.currency'),
        ])->setPaper('a4')->stream("commission-statement-{$commissionPayout->statementNumber()}.pdf")->withHeaders([
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
