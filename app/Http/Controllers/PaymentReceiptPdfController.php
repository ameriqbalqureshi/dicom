<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Services\Billing\PaymentReceiptPdf;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class PaymentReceiptPdfController extends Controller
{
    /**
     * Show the receipt for a confirmed payment as a PDF.
     */
    public function __invoke(Subscription $subscription, PaymentReceiptPdf $pdf): Response
    {
        Gate::authorize('viewReceipt', $subscription);

        return $pdf->stream($subscription);
    }
}
