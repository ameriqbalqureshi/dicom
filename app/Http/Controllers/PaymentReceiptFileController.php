<?php

namespace App\Http\Controllers;

use App\Models\PaymentSubmission;
use App\Models\Subscription;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shows administrators the receipts buyers uploaded.
 */
class PaymentReceiptFileController extends Controller
{
    public function subscription(Subscription $subscription): Response
    {
        return $this->show($subscription->receipt_path);
    }

    public function submission(PaymentSubmission $paymentSubmission): Response
    {
        return $this->show($paymentSubmission->receipt_path);
    }

    private function show(?string $path): Response
    {
        $disk = Storage::disk(Subscription::RECEIPT_DISK);

        abort_if($path === null || ! $disk->exists($path), 404);

        return $disk->response($path, 'receipt.'.pathinfo($path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
