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

    'hrm_employee' => [
        'url' => env('HRM_EMPLOYEE_URL'),
        'updated_param' => env('HRM_EMPLOYEE_UPDATED_PARAM', 'updatedAt'),
        'verify_ssl' => env('HRM_EMPLOYEE_VERIFY_SSL', true),
    ],

    'ldap' => [
        'url' => env('LDAP_URL'),
        'base_dn' => env('LDAP_BASE_DN'),
        'bind_dn' => env('LDAP_BIND_DN'),
        'bind_password' => env('LDAP_BIND_PASSWORD'),
        'user_attribute' => env('LDAP_USER_ATTRIBUTE', 'sAMAccountName'),
        'user_dn_template' => env('LDAP_USER_DN_TEMPLATE'),
        'start_tls' => env('LDAP_START_TLS', false),
        'timeout' => env('LDAP_TIMEOUT', 5),
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
