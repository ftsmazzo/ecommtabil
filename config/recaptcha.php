<?php
return [
    "auth" => env("RECAPTCHA_AUTH", true),
    "version" => env("RECAPTCHA_VERSION", 3),
    "site_key" => env("RECAPTCHA_SITE_KEY", ""),
    "secret_key" => env("RECAPTCHA_SECRET_KEY", ""),
    "score" => env("RECAPTCHA_SCORE", .5), // 0 ~ 1
];
