<?php
namespace App\Core;

use \App\Core\Config;

class Cors
{

    protected $origin;
    protected $methods;
    protected $headers;

    public function __construct()
    {

        $config = Config::get("cors");

        $this->origin = $config["origin"];
        $this->methods = implode(",", $config["methods"]);
        $this->headers = implode(",", $config["headers"]);
    }

    public function enable()
    {

        header("Access-Control-Allow-Origin: {$this->origin}");
        header("Access-Control-Allow-Methods: {$this->methods}");
        header("Access-Control-Allow-Headers: {$this->headers}");
        // header('Content-Type: application/json');

        $method = $_SERVER['REQUEST_METHOD'];
        if ($method == "OPTIONS") {
            header("Access-Control-Allow-Origin: {$this->origin}");
            header("Access-Control-Allow-Headers: {$this->headers}");
            header("HTTP/1.1 200 OK");
            die();
        }
    }


}
