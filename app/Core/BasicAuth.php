<?php
namespace App\Core;

class BasicAuth
{

    private static $config;
    private static $username;
    private static $password;
    private static $ip;

    public function __construct()
    {

        self::$config = Config::get("basic");

        self::$username = self::$config["username"];

        self::$password = self::$config["password"];

        self::$ip = self::$config["ip"];
    }

    public static function generate()
    {
        new BasicAuth();
        return base64_encode(self::$username . ":" . self::$password);
    }

    public static function decode($token) : array
    {

        new BasicAuth();

        list($user, $pass) = explode(":", base64_decode($token));

        $basic["username"] = $user;
        $basic["password"] = $pass;
        if (self::$ip) $basic["ip"] = self::$ip;

        return $basic;
    }

    public static function validate($token) : bool
    {

        if (!$token)
            return false;

        $tok = self::generate();

        if ($token !== $tok)
            return false;

        $s = $_SERVER;

        if (!isset($s['PHP_AUTH_USER'])
            || !isset($s['PHP_AUTH_PW'])
            || empty($s['PHP_AUTH_USER'])
            || empty($s['PHP_AUTH_PW'])
        ) {
            return false;
        }

        if (self::$username !== $s['PHP_AUTH_USER'])
            return false;

        if (self::$password !== $s['PHP_AUTH_PW'])
            return false;

        if (self::$ip && self::$ip !== $s["REMOTE_ADDR"])
            return false;

        return true;
    }

}
