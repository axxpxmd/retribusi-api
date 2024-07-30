<?php

return [
    'key' => env('APP_KEY'),
    'cipher' => env('APP_CIPHER', 'AES-256-CBC'),

    // Lock IP
    'ipbjb' => env('IP_BJB'),
    'ipbjb2' => env('IP_BJB2'),
    'ipkmnf' => env('IP_KMNF'),

    // VA BJB
    'cin_bjb' => env('CIN_BJB'),
    'ip_api_bjb' => env('IP_API_BJB'),
    'key_bjb' => env('KEY_BJB'),
    'client_id_bjb' => env('CLIENT_ID_BJB'),

    // QRIS BJB
    'msisdn_bjb' => env('MSISDN_BJB'),
    'password_bjb' => env('PASSWORD_BJB'),
    'ip_qris' => env('IP_QRIS'),
    'app_id_qris' => env('APP_ID_QRIS'),

    // Whatsapp
    'wagateway_ipserver' => env('WAGATEWAY_IPSERVER'),
    'wagateway_apikey_login' => env('WAGATEWAY_APIKEY_LOGIN'),
    'wagateway_apikey' => env('WAGATEWAY_APIKEY'),

    'url_retribusi' => env('URL_RETRIBUSI'),
    'url_sftp' => env('URL_SFTP'),

    'timezone' => 'UTC',
    'timezone' => 'Asia/Jakarta',

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Lumen's queue API supports an assortment of back-ends via a single
    | API, giving you convenient access to each back-end using the same
    | syntax for every one. Here you may define a default connection.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'sync'),

    'timezone' => 'Asia/Jakarta',

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection information for each server that
    | is used by your application. A default configuration has been added
    | for each back-end shipped with Lumen. You are free to add more.
    |
    | Drivers: "sync", "database", "beanstalkd", "sqs", "redis", "null"
    |
    */

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'table' => env('QUEUE_TABLE', 'jobs'),
            'queue' => 'default',
            'retry_after' => 90,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => 'localhost',
            'queue' => 'default',
            'retry_after' => 90,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => env('SQS_KEY', 'your-public-key'),
            'secret' => env('SQS_SECRET', 'your-secret-key'),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('SQS_QUEUE', 'your-queue-name'),
            'region' => env('SQS_REGION', 'us-east-1'),
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('QUEUE_REDIS_CONNECTION', 'default'),
            'queue' => 'default',
            'retry_after' => 90,
            'block_for' => null,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control which database and table are used to store the jobs that
    | have failed. You may change them to any database / table you wish.
    |
    */

    'failed' => [
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => env('QUEUE_FAILED_TABLE', 'failed_jobs'),
    ],
];
