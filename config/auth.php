<?php
return [

    'auth_sessions_table' => 'auth_sessions',

    'route_redirect' => 'admin.home', // route name

    'security' => [

        // Throttle (anti brute force)
        'throttle' => [
            'enabled' => true,
            'window_sec' => 600,    // 10 min
            'max_login_ip' => 8,    // por guard+login+ip
            'max_ip' => 25,         // por guard+ip
            'base_lock_sec' => 600, // 10 min
            'max_lock_sec' => 3600, // 1h
            'ip_hash_salt' => '8e4c7a91f3b2d5c6a8e9f1b4d2c7e5a9f0b3c6d8e1a4f7c2d5b9e6a1f3c8d4e2',

            // opcional: override por guard
            'guards' => [
                // 'usuario' => ['max_login_ip' => 5, 'max_ip' => 15],
            ],
        ],

        // Rate limits adicionais para fluxos sensiveis que nao sao login.
        'rate_limits' => [
            'password_request' => [
                'enabled' => true,
                'window_sec' => 3600,       // 1h
                'max_subject_ip' => 3,      // mesmo email+ip
                'max_ip' => 10,             // mesmo ip
                'base_lock_sec' => 1800,    // 30 min
                'max_lock_sec' => 7200,     // 2h
            ],
            'password_reset' => [
                'enabled' => true,
                'window_sec' => 1800,       // 30 min
                'max_subject_ip' => 5,      // mesmo token+ip
                'max_ip' => 20,             // mesmo ip
                'base_lock_sec' => 900,     // 15 min
                'max_lock_sec' => 3600,     // 1h
            ],
        ],

        'password_policy' => [
            'min_length' => env('AUTH_PASSWORD_MIN_LENGTH', 5),
        ],

        // Sessões (hijack / lifetime)
        'session' => [
            'idle_timeout_sec' => 14400,     // 4h sem atividade
            'absolute_timeout_sec' => 43200, // 12h total
            'activity_ping_sec' => 60,       // atualiza last_activity no máximo 1x/min
        ],

        // Policy de binding
        'binding' => [
            // Em painéis web, o User-Agent pode variar por extensão, WebView,
            // atualização do navegador ou proxies intermediários. Desligado
            // por padrão para evitar logout espúrio.
            'bind_user_agent' => false,
            'ip_mode' => 'policy', // policy | strict | off
        ],
    ],

    'guards' => [

        'usuario' => [
            'driver' => 'session',
            'table' => 'usuario',
            'authname' => 'dcf_admin_usuario',
            'scenery' => 'admin',
            'check_online' => 'allow_all', // 'deny' para bloquear, 'allow_other_ip' para permitir outras sessões com ips diferentes, 'allow_all' para permitir sempre
            'history' => true, // save history
            'table_history' => 'usuario_historico',
            'mediapath' => 'usuarios',
            'routes' => [
                'login' => 'admin.login', // route name
                'signin' => 'admin.logar', // route name
                'logout' => 'admin.logout', // route name
                'redirect' => 'admin.home', // route name
                'forgot_password' => 'admin.password', // route name
                'forgot_password_request' => 'admin.password.request', // route name
                'reset_password' => 'admin.password.reset', // route name
                'reset_password_update' => 'admin.password.update', // route name
            ],
            'features' => [
                'forgot_password' => false,
            ],
            'columns' => [
                // fields of user table, false for ignore
                'id' => 'id',
                'name' => 'nome',
                'nickname' => false,
                'photo' => 'foto',
                'login' => 'login',
                'token' => 'token',
                'password' => 'senha',
                'status' => 'status',
                'validate' => false,
                'permissao' => 'permissoes',
            ],
            'types' => [
                'login' => 'text', //type of login: email or text
                'status' => [
                    "active_val" => 1,
                    "inactive_val" => 0,
                ],
                'validate' => [
                    "valid_val" => 1,
                    "invalid_val" => 0,
                ],
            ],
            'permissions' => [
                'table' => 'usuario_permissao', // tabela com os registros das permissões
                'column' => 'permissao', // coluna que armazena o nome da permissão
            ],
        ],

        "facebook" => [
            "authname" => "",
            "clientId" => "",
            "clientSecret" => "",
            "redirectUri" => URL_BASE . "/login/facebook",
            "graphApiVersion" => "v10.0"
        ],

        "google" => [
            "authname" => "",
            "clientId" => "",
            "clientSecret" => "",
            "redirectUri" => URL_BASE . "/login/google",
        ],
    ],
];
