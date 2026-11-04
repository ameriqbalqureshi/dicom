<?php

namespace App\Services\Billing;

use App\Models\Subscription;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders the numbered PDF receipt a buyer gets once their payment is confirmed.
 */
class PaymentReceiptPdf
{
    /**
     * Show the PDF in the browser.
     */
    public function stream(Subscription $subscription): Response
    {
        return $this->build($subscription)->stream($this->filename($subscription))->withHeaders([
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * The PDF's contents, for attaching to an email.
     */
    public function render(Subscription $subscription): string
    {
        return $this->build($subscription)->output();
    }

    public function filename(Subscription $subscription): string
    {
        return "payment-receipt-{$subscription->receiptNumber()}.pdf";
    }

    private function build(Subscription $subscription): DomPdf
    {
        $subscription->loadMissing(['user', 'package']);

        return Pdf::loadView('billing.receipt-pdf', [
            'subscription' => $subscription,
            'currency' => config('billing.currency'),
        ])->setPaper('a4');
    }
}
