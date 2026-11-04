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
    | Payment accounts
    |--------------------------------------------------------------------------
    |
    | Administrators manage the accounts buyers pay into on the Payment accounts
    | page. These values are only read once, when the migration that created
    | that page brings across any accounts set up here before it existed.
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
    | The instructions shown to buyers until an administrator changes them on
    | the Payment accounts page.
    |
    */

    'payment_instructions' => env('BILLING_PAYMENT_INSTRUCTIONS', 'Pay the package price into one of our accounts, then send us your receipt. Your package starts as soon as we confirm the payment.'),

];
