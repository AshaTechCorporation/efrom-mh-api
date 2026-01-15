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

    // 'facebook' => [
    //     'client_id' => '733906460725761',
    //     'client_secret' => '8650482ed058dc930a02090217d02acc',
    //     'redirect' => 'http://localhost/asha/Affiliate/Affiliate-api/public/login/facebook/callback',
    // ],

    // 'google' => [
    //     'client_id'     => '703305436277-mcpcoogi1mbqvl8ln5a1buppoesuo5ds.apps.googleusercontent.com',
    //     'client_secret' => 'Tj8h0zrVp8mqV2eTrEf-yLc_',
    //     'redirect'      => 'http://localhost/asha/Affiliate/Affiliate-api/public/login/google/callback'
    // ],

];
