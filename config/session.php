<?php
return [
    'driver' => 'file',

    'path' => __DIR__ . '/../storage/sessions',

    // Mantemos acima do idle timeout do Auth para a sessão PHP não expirar
    // antes da política de autenticação do sistema.
    'lifetime' => 720, // minutos

    'regenerate_on_auth' => false,

    'cookie' => [
        'days' => 7,
        'path' => '/',
    ],
];
