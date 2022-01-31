<?php

return [
    'key' => env('APP_KEY'),
    'cipher' => env('APP_CIPHER', 'AES-256-CBC'),

    // Lock IP
    'ipbjb' => env('IP_BJB'),
    'ipbjb2' => env('IP_BJB2'),
    'ipkmnf' => env('IP_KMNF'),

    // BJB
    'cin_bjb' => env('CIN_BJB'),
    'ip_api_bjb' => env('IP_API_BJB'),
    'key_bjb' => env('KEY_BJB'),
    'client_id_bjb' => env('CLIENT_ID_BJB'),
];
