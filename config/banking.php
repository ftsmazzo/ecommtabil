<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ambiente do sistema
    |--------------------------------------------------------------------------
    | sandbox | production
    */
    'environment' => 'sandbox',

    /*
    |--------------------------------------------------------------------------
    | Bancos suportados pelo sistema
    |--------------------------------------------------------------------------
    | Apenas metadados e endpoints base
    | Credenciais vêm do cliente
    */
    'banks' => [

        'banco_do_brasil' => [
            'name' => 'Banco do Brasil',
            'supports' => ['accounts', 'transactions', 'pix', 'billing'],
            'sandbox' => [
                'oauth_base_uri' => 'https://oauth.sandbox.bb.com.br',
                'api_base_uri'   => 'https://api.sandbox.bb.com.br',
            ],
            'production' => [
                'oauth_base_uri' => 'https://oauth.bb.com.br',
                'api_base_uri'   => 'https://api.bb.com.br',
            ],
        ],

        'inter' => [
            'name' => 'Banco Inter',
            'supports' => ['accounts', 'transactions', 'pix', 'billing'],
            'sandbox' => [
                'oauth_base_uri' => 'https://cdpj.partners.bancointer.com.br/oauth/v2',
                'api_base_uri'   => 'https://cdpj.partners.bancointer.com.br',
            ],
            'production' => [
                'oauth_base_uri' => 'https://cdpj.partners.bancointer.com.br/oauth/v2',
                'api_base_uri'   => 'https://cdpj.partners.bancointer.com.br',
            ],
        ],

        'sicredi' => [
            'name' => 'Sicredi',
            'supports' => ['accounts', 'transactions', 'pix'],
            'sandbox' => [
                'oauth_base_uri' => 'https://sandbox.api.sicredi.com.br/oauth',
                'api_base_uri'   => 'https://sandbox.api.sicredi.com.br',
            ],
            'production' => [
                'oauth_base_uri' => 'https://api.sicredi.com.br/oauth',
                'api_base_uri'   => 'https://api.sicredi.com.br',
            ],
        ],

        'sicoob' => [
            'name' => 'Sicoob',
            'supports' => ['accounts', 'transactions', 'pix'],
            'sandbox' => [
                'oauth_base_uri' => 'https://sandbox.sicoob.com.br/oauth',
                'api_base_uri'   => 'https://sandbox.sicoob.com.br',
            ],
            'production' => [
                'oauth_base_uri' => 'https://api.sicoob.com.br/oauth',
                'api_base_uri'   => 'https://api.sicoob.com.br',
            ],
        ],

        'pagbank' => [
            'name' => 'PagBank',
            'supports' => ['transactions', 'pix'],
            'sandbox' => [
                'api_base_uri' => 'https://sandbox.api.pagseguro.com',
            ],
            'production' => [
                'api_base_uri' => 'https://api.pagseguro.com',
            ],
        ],

        'mercadopago' => [
            'name' => 'Mercado Pago',
            'supports' => ['transactions', 'pix'],
            'sandbox' => [
                'api_base_uri' => 'https://api.mercadopago.com',
            ],
            'production' => [
                'api_base_uri' => 'https://api.mercadopago.com',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Segurança
    |--------------------------------------------------------------------------
    */
    'security' => [
        'encrypt_credentials' => true,
        'credentials_cipher'  => 'AES-256-GCM',
    ],
];
