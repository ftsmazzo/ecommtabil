<?php
return [

    "default" => "naoresponda",

    "accounts" => [

        "naoresponda" => [
            "smtp" => [
                "host" => env("MAIL_HOST", "smtp.uni5.net"),
                "user" => env("MAIL_USER", "naoresponda@diz.dev.br"),
                "pass" => env("MAIL_PASS", ""),
                "port" => env("MAIL_PORT", 465),
                "secure" => env("MAIL_SECURE", "ssl"), // ssl, tls
            ],
            "sender" => [
                "name" => env("MAIL_SENDER_NAME", "Sistema"),
                "email" => env("MAIL_SENDER_EMAIL", "naoresponda@diz.dev.br"),
            ],
            "option" => [
                "auth" => env("MAIL_AUTH", true),
                "html" => env("MAIL_HTML", true),
                "lang" => env("MAIL_LANG", "br"),
                "charset" => env("MAIL_CHARSET", "utf-8"),
                "debug" => env("MAIL_DEBUG", false), // 1, 2
                "debug_die" => env("MAIL_DEBUG_DIE", true),
            ],
        ],

        // "intranet" => [
        //     "smtp" => [
        //         "host" => "smtp.uni5.net",
        //         "user" => "intranet@grupodcf.com.br",
        //         "pass" => "Aean#NW#*SFAfz0",
        //         "port" => 465,
        //         "secure" => "ssl", // ssl, tls
        //     ],
        //     "sender" => [
        //         "name" => "Intranet DCF",
        //         "email" => "intranet@grupodcf.com.br",
        //     ],
        //     "option" => [
        //         "auth" => true,
        //         "html" => true,
        //         "lang" => "br",
        //         "charset" => "utf-8",
        //         "debug" => false, // 1, 2
        //         "debug_die" => true,
        //     ],
        // ],

    ]

];
