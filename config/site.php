<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public website
    |--------------------------------------------------------------------------
    |
    | Details shown on the public pages (home, pricing, about, contact) and in
    | the site footer. Leave a value empty to hide it.
    |
    */

    'tagline' => env('SITE_TAGLINE', 'Share, view and report medical imaging from any browser.'),

    'contact' => [
        'email' => env('SITE_CONTACT_EMAIL', env('MAIL_FROM_ADDRESS')),
        'phone' => env('SITE_CONTACT_PHONE'),
        'address' => env('SITE_CONTACT_ADDRESS'),
        'hours' => env('SITE_CONTACT_HOURS', 'Monday to Saturday, 9:00 to 18:00'),
    ],

];
