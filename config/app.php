<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Whatsapp Gateway
    |--------------------------------------------------------------------------
    */

    'wa_1' => env('WAGATEWAY_IPSERVER'),
    'wagateway_ipserver' => env('WAGATEWAY_IPSERVER'),
    'wagateway_apikey_login' => env('WAGATEWAY_APIKEY_LOGIN'),
    'wagateway_apikey' => env('WAGATEWAY_APIKEY'),

    /*
    |--------------------------------------------------------------------------
    | Sisumaker Center
    |--------------------------------------------------------------------------
    */

    'sc_api' => env('SC_PROTOCOL') . '://' .  env('SC_HOST', '') . env('SC_URL', ''),


    /*
    |--------------------------------------------------------------------------
    | Host Whatsapp Center
    |--------------------------------------------------------------------------
    */

    'wa_send' => (bool) env('WA_SEND', false),
    'wa_host' => env('WA_HOST', ''),
    'wa_key' => env('WA_KEY', ''),
    'wa_number' => env('WA_NUMBER', ''),

    'key' => env('APP_KEY'),
    'cipher' => env('APP_CIPHER', 'AES-256-CBC'),

    /*
    |--------------------------------------------------------------------------
    | SFTP
    |--------------------------------------------------------------------------
    */
    'sftp_src' => env('SFTP_SRC'),
];
