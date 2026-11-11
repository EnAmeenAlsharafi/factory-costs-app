<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Web Push (VAPID)
    |--------------------------------------------------------------------------
    |
    | Lets a notification reach a phone whose screen is off, through the browser's
    | own push service. Generate the pair once with:
    |
    |     php artisan notifications:vapid
    |
    | and paste the two lines it prints into .env. With no keys the application
    | simply never pushes; everything else about notifications keeps working.
    |
    */

    'public_key' => env('WEBPUSH_PUBLIC_KEY'),

    'private_pem' => env('WEBPUSH_PRIVATE_PEM'),

    // Whom the push service may contact about this sender; a mailto: or https: address.
    'subject' => env('WEBPUSH_SUBJECT', 'mailto:info@sadir-factory.com'),

];
