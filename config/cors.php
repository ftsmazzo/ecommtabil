<?php
    return [

        "origin" => "*",

        "methods" => ["HEAD", "GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"],

        "headers" => ["X-API-KEY", "Origin", "X-Requested-With", "Content-Type", "Accept", "Access-Control-Request-Method", "Access-Control-Request-Headers", "Authorization"],
    ];