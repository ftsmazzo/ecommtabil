<?php
namespace App\Core;

class GoogleFont
{

    public static $fonts;

    public function __construct()
    {

        self::$fonts = Config::get("google_fonts");

    }

    public static function list()
    {
        new self;
        return self::$fonts;
    }

}
