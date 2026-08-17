<?php
return [

    'default' => 'sandbox', // Default Connection

    'connections' => [

        'sandbox' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', 'localhost'),
            'database' => env('DB_DATABASE', 'sistema'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'port' => env('DB_PORT', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
        ],

    ]

];
