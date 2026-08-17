<?php
namespace App\Core;

class ApiKey
{

    private static $config;
    private static $key;
    private static $value;
    private static $local;
    private static $ip;

    public function __construct()
    {

        self::$config = Config::get("apikey");

        self::$key = !empty(self::$config["key"]) ? self::$config["key"] : "X-API-KEY";

        self::$value = self::$config["value"];

        self::$local = self::$config["local"];

        self::$ip = self::$config["ip"];
    }

    public static function generate($length=30, $upper=null, $lower=null, $number=null, $symbol=null)
    {
        if (is_null($upper))  $upper = "ABCDEFGHIJKLMNOPQRSTUVWXYZ"; // contem as letras maiúsculas
        if (is_null($lower))  $lower = strtolower($upper); // contem as letras minusculas
        if (is_null($number)) $number= "0123456789"; // contem os números
        if (is_null($symbol)) $symbol= "!@#$%&*"; // contem os símbolos

        $string = str_shuffle($upper).str_shuffle($lower).str_shuffle($number).str_shuffle($symbol);

        $hash = substr(str_shuffle($string), 0, $length);

        return $hash;
    }

    public static function validate() : bool
    {

        new ApiKey();

        if (!isset(self::$value) || empty(self::$value || is_null(self::$value)))
            return false;

        $headers = getallheaders();

        if (!array_key_exists(self::$key, $headers))
            return false;

        $s = $_SERVER;

        $server_key = "HTTP_" . strtoupper(self::$key);

        if (!array_key_exists($server_key, $s))
            return false;

        if (empty($headers[self::$key]) || $headers[self::$key] !== self::$value)
            return false;

        if (empty($s[$server_key]) || $s[$server_key] !== self::$value)
            return false;

        if (self::$ip && self::$ip !== $s["REMOTE_ADDR"])
            return false;

        return true;
    }

}
