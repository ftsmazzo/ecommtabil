<?php

namespace App\Core;

use League\Plates\Engine;

class View
{

    /** @var Engine */
    private $engine;

    /** @var Config */
    public $config;

    public function __construct()
    {
        $this->config = Config::get("view");
        $this->engine = new Engine(
            $this->config["path"],
            $this->config["extension"]
        );
    }

    public function path(string $name, string $path): View
    {
        $this->engine->addFolder($name, $path);
        return $this;
    }

    public function addData(array $data)
    {
        return $this->engine->addData($data);
    }

    public function render(string $templateName, array $data = []): string
    {
        return $this->engine->render($templateName, $data);
    }

    public function engine(): Engine
    {
        return $this->engine();
    }

    public function include(string $name, array $data = [])
    {
        extract($data);
        ob_start();
        include $this->config["path"] . $name . "." . $this->config["extension"];
        return ob_get_clean();
    }

}
