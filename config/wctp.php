<?php

return [

    /*
    |--------------------------------------------------------------------------
    | TLS Settings
    |--------------------------------------------------------------------------
    |
    | Control TLS certificate verification when communicating with
    | enterprise hosts. Disable only in development environments.
    |
    */

    'verify_certificates' => env('WCTP_VERIFY_CERTIFICATES', true),

    /*
    |--------------------------------------------------------------------------
    | Twilio Settings
    |--------------------------------------------------------------------------
    |
    | Enable Twilio webhook signature validation to ensure incoming
    | requests on callback/SMS routes are genuinely from Twilio.
    |
    */

    'validate_twilio_signatures' => env('WCTP_VALIDATE_TWILIO_SIGNATURES', true),

    /*
    |--------------------------------------------------------------------------
    | Forwarding Settings
    |--------------------------------------------------------------------------
    |
    | Configure the async forwarding of messages to enterprise hosts.
    |
    */

    'forwarding' => [
        'timeout' => env('WCTP_FORWARDING_TIMEOUT', 30),
        'retries' => env('WCTP_FORWARDING_RETRIES', 10),
        'retry_delay' => env('WCTP_FORWARDING_RETRY_DELAY', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Processing Settings
    |--------------------------------------------------------------------------
    |
    | Configure retry behavior for outbound message processing jobs.
    |
    */

    'processing' => [
        'retries' => env('WCTP_PROCESSING_RETRIES', 5),
        'retry_until_minutes' => env('WCTP_PROCESSING_RETRY_UNTIL_MINUTES', 30),
    ],

];
