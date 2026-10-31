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
    | Payment instructions
    |--------------------------------------------------------------------------
    |
    | Shown to buyers before they submit a purchase, e.g. the account to pay.
    |
    */

    'payment_instructions' => env('BILLING_PAYMENT_INSTRUCTIONS', 'Pay the package price to our account, then enter the transaction reference below. Your package starts as soon as we confirm the payment.'),

];
