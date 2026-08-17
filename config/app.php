<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Configurações principais do sistema
    |--------------------------------------------------------------------------
    */

    'environment' => env('APP_ENV', 'sandbox'),
    'name'        => env('APP_NAME', 'Sistema'),
    'path'        => env('APP_PATH', ''),
    'timezone'    => env('APP_TIMEZONE', 'America/Sao_Paulo'),
    'path_app'    => 'app',

    /*
    |--------------------------------------------------------------------------
    | Valores globais do sistema
    |--------------------------------------------------------------------------
    */
    'globals' => [
        'multi_language' => false,
        'default_language' => 'pt-BR',
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuração para uso de Python
    |--------------------------------------------------------------------------
    */
    'python' => [
        'command' => PHP_OS_FAMILY === 'Windows' ? 'C:\laragon\bin\python\python-3.13\python.exe' : '/usr/bin/python3',
        'command_py' => PHP_OS_FAMILY === 'Windows' ? 'pip' : '/usr/bin/py3',
    ],

    /*
    |--------------------------------------------------------------------------
    | Fontes e versões externas
    |--------------------------------------------------------------------------
    */
    'icons' => [
        'font_awesome_version' => '6.7.2',
        'font_unicons_version' => '4.2.0',
        'font_tabler_version' => 'latest',
    ],

    /*
    |--------------------------------------------------------------------------
    | Layouts e temas
    |--------------------------------------------------------------------------
    */
    'sceneries' => [

        'admin' => [
            'case'  => 'upper', // upper, normal (upper apenas nos campos selecionados no Model)
            'color' => 'light', // clean, light, dark
            'preference_model' => \App\Models\UsuarioPreferencia::class,
            'menu_service'     => \App\Services\MenuService::class,

            'layout' => [
                'content_container' => 'container-extra',
                'menu' => [
                    'type' => 'horizontal', // vertical, horizontal
                    'size' => 'compact', // default, compact
                    'icons' => true,
                    'container' => 'container-extra', // container, container-fluid, container-extra
                ],
                'topbar' => [
                    'logo_height'    => '40',
                    'logo_height_sm' => '20',
                    'logo_route'     => 'admin.home',
                    'container'      => 'container-extra', // container, container-fluid, container-extra
                ],
                'custom-js'             => 'admin/js/admin.min.js',
                'logo_login'            => 'logo.png',
                'logo_sidebar'          => 'logo-sidebar.png',
                'logo_mobile'           => 'logo.png',
                'logo_light'            => 'logo.png',
                'logo_dark'             => 'logo-dark.png',
                'background_login'      => '',
            ],
        ],

        'cliente' => [
            'case'  => 'upper', // upper, normal
            'menu'  => 'vertical', // vertical, horizontal
            'theme' => 'vertical',
            'fluid' => true,
            'color' => 'light',
            'preference_model' => \App\Models\UserPreferencia::class,
            'menu_service'     => \App\Services\MenuService::class,

            'layout' => [
                'content_container' => 'container-fluid',
                'menu' => [
                    'type' => 'vertical', // vertical, horizontal
                    'icons' => true,
                    'container' => 'container-fluid', // container, container-fluid, container-extra
                ],
                'template'              => 'vertical',
                'leftbar-theme'         => 'dark',
                'leftbar-compact-mode'  => 'fixed',
                'rightbar-onstart'      => 'false',
                'topbar' => [
                    'logo_height'    => '48',
                    'logo_height_sm' => '20',
                    'logo_route'     => 'cliente.home',
                    'container'      => 'container-fluid', // container, container-fluid, container-extra
                ],
                'custom-js'             => 'cliente/js/cliente.min.js',
                'logo_login'            => 'logo.png',
                'logo_sidebar'          => 'logo.png',
                'logo_mobile'           => 'logo_small.png',
                'logo_light'            => 'logo.png', // storage/layouts
                'logo_dark'             => 'logo-dark.png', // storage/layouts
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Segurança e chaves
    |--------------------------------------------------------------------------
    */
    'security' => [
        'encrypt_key' => env('APP_ENCRYPT_KEY', '5d7b4c7d6da7b9dbea6a222cbe06fb14'),
        'cookie_name' => env('APP_COOKIE_NAME', 'lgpd_dcf'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Outras opções gerais
    |--------------------------------------------------------------------------
    */
    'uploads' => [
        'path' => 'storage/media/uploads',
    ],


];
