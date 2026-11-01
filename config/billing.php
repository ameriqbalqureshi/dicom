<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | Package prices are stored as whole amounts in this currency.
    |
    */

    'currency' => env('BILLING_CURRENCY', 'PKR'),

    /*
    |--------------------------------------------------------------------------
    | Payment methods
    |--------------------------------------------------------------------------
    |
    | Payments are made outside the application. The buyer chooses how they
    | paid and enters the transaction reference, and an administrator
    | confirms the payment before the package is activated.
    |
    */

    'payment_methods' => [
        'bank_transfer' => 'Bank transfer',
        'jazzcash' => 'JazzCash',
        'easypaisa' => 'Easypaisa',
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment accounts
    |--------------------------------------------------------------------------
    |
    | The accounts buyers pay into, for each payment method above, shown at
    | checkout and on the pricing page. Empty details are not shown, and a
    | method without any details shows only the payment instructions.
    |
    */

    'payment_accounts' => [
        'bank_transfer' => [
            'Bank' => env('BILLING_BANK_NAME'),
            'Account title' => env('BILLING_BANK_ACCOUNT_TITLE'),
            'Account number' => env('BILLING_BANK_ACCOUNT_NUMBER'),
            'IBAN' => env('BILLING_BANK_IBAN'),
            'Branch' => env('BILLING_BANK_BRANCH'),
        ],
        'jazzcash' => [
            'Account title' => env('BILLING_JAZZCASH_ACCOUNT_TITLE'),
            'Mobile number' => env('BILLING_JAZZCASH_NUMBER'),
        ],
        'easypaisa' => [
            'Account title' => env('BILLING_EASYPAISA_ACCOUNT_TITLE'),
            'Mobile number' => env('BILLING_EASYPAISA_NUMBER'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment instructions
    |--------------------------------------------------------------------------
    |
    | Shown to buyers before they submit a purchase, e.g. the account to pay.
    |
    */

    'payment_instructions' => env('BILLING_PAYMENT_INSTRUCTIONS', 'Pay the package price to our account, then enter the transaction reference below. Your package starts as soon as we confirm the payment.'),

];
