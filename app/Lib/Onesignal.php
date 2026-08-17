<?php
namespace App\Lib;

use App\Core\Config;

class Onesignal
{

    /** @var Config */
    private static $config;
    private static $app_id;
    private static $data;
    private static $url;
    private static $base_url;

    public function __construct()
    {

        self::$config = Config::get("onesignal");

        self::$app_id = self::$config["app_id"];

        self::$base_url = "https://onesignal.com/api/v1";

    }

    public static function setAppId($app_id)
    {
        self::$app_id = $app_id;
    }

    public static function sendNotification($usr_id, $title=null, $text=null, $url=null)
    {
        new Onesignal();

        if (!self::$app_id)
            die("The App Id is required");

        if ($title)
            $headings["en"] = $title;

        if ($text)
            $contents["en"] = $text;

        $players = [
            $usr_id
        ];

        // Fields
        $data["app_id"] = self::$app_id;
        $data["include_player_ids"] = $players;

        if (isset($headings))
            $data["headings"] = $headings;

        if (isset($contents))
            $data["contents"] = $contents;

        if ($url)
            $data["web_url"] = $url;

        $endpoint = self::$base_url."/notifications";

        $header = ['Content-Type: application/json; charset=utf-8'];

        self::request($endpoint, $data, $header, "POST");
    }

    private static function request($url, $data, $header=null, $method)
    {

        if (!$url)
            die("The url is required!");

        if (!$data)
            die("The fields is required!");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);

        if ($header)
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        switch ($method) {
            case 'GET':
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                break;
            default:
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        if ($header) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }

        if (!is_json($data))
            $fields = json_encode($data);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

}

/* Helpers */
function is_json($string) {
    if (!is_string($string)) {
        return false;
    } else {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }
}
