<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Parametrizacoes por modulo
    |--------------------------------------------------------------------------
    */
    'cliente' => [
        'default_pessoa' => 'J', // F = pessoa fisica, J = pessoa juridica
        'allowed_pessoa' => ['F', 'J'],
        'use_ramo' => true,
    ],
];
