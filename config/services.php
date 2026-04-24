<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | GA Engine (Python FastAPI)
    |--------------------------------------------------------------------------
    | url      : base URL Python FastAPI, default localhost:8001
    | timeout  : detik tunggu sebelum timeout (GA bisa lama)
    | use_mock : true = pakai mock (testing), false = hubungi Python langsung
    */
    'ga_engine' => [
        'url'      => env('GA_ENGINE_URL', 'http://127.0.0.1:8001'),
        'timeout'  => env('GA_ENGINE_TIMEOUT', 120),
        'use_mock' => env('GA_ENGINE_USE_MOCK', true),
    ],

];
