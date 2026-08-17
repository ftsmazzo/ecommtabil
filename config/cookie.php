<?php
return [

    'days' => env('COOKIE_DAYS', 24), // duração padrão de cookies persistentes

    'path' => '/',

    // Segurança
    'secure' => env('COOKIE_SECURE', isset($_SERVER['HTTPS'])), // só envia via HTTPS
    'http_only' => env('COOKIE_HTTP_ONLY', true), // bloqueia acesso via JS
    'samesite' => env('COOKIE_SAMESITE', 'Lax'), // protege contra CSRF
    'cookie_secret' => env('COOKIE_SECRET', 'iu16B469lctKBCl344xsuoGLRatNpk7H'),
];
