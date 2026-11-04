<?php

namespace App\Services\Billing;

use App\Models\Setting;

/**
 * The payment settings administrators manage. Until they change them, the defaults come from the configuration.
 */
class BillingSettings
{
    private const PAYMENT_INSTRUCTIONS = 'billing.payment_instructions';

    private const RECEIPT_EMAIL = 'billing.receipt_email';

    public function paymentInstructions(): string
    {
        return Setting::find(self::PAYMENT_INSTRUCTIONS)?->value ?: (string) config('billing.payment_instructions');
    }

    /**
     * The address buyers can email their receipts to.
     */
    public function receiptEmail(): ?string
    {
        return Setting::find(self::RECEIPT_EMAIL)?->value ?: config('site.contact.email');
    }

    public function update(string $paymentInstructions, ?string $receiptEmail): void
    {
        Setting::updateOrCreate(['key' => self::PAYMENT_INSTRUCTIONS], ['value' => $paymentInstructions]);
        Setting::updateOrCreate(['key' => self::RECEIPT_EMAIL], ['value' => $receiptEmail]);
    }
}
