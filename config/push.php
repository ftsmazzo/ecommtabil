<?php
return [
    'publicKey'  => env('PUSH_PUBLIC_KEY', ''),
    'privateKey' => env('PUSH_PRIVATE_KEY', ''),
    'subject'    => env('PUSH_SUBJECT', 'mailto:naoresponda@grupodcf.com.br'), // recomendado mas opcional
];
