<?php

namespace App\Core;

use \Monolog\Logger;
use \Monolog\Handler\StreamHandler;

class Log
{
    private $file;

    /** @var Config */
    private $config;

    private $path;

    public function __construct()
    {

        $this->config = Config::get("log");

        $this->path = $this->config["path"];
    }

    public function app(string $message, array $data = [], bool $extra = true): void
    {
        $messages = [
            'insert' => "INSERT", //'Um novo registro foi inserido',
            'update' => "UPDATE", //'Um registro foi alterado',
            'delete' => "DELETE", //'Um registro foi excluído',
            'delete_file' => "DELETE FILE", //'Um registro foi excluído',
            'status' => "STATUS", //'Um resgitro foi alterado o status',
            'login'  => "LOGIN" , //'Entrada no sistema',
            'logout' => "LOGOUT", //'Saída do sistema',
            'deletecheck' => "DELETECHECK", //'Vários registros foram excluídos',
            'statuscheck' => "STATUSCHECK", //'Vários registros tiveram o status alterado',
            'pass' => "UPDATE PASS", //'Atualizou a sua senha',
        ];

        $text = array_key_exists($message, $messages) ? $messages[$message] : $message;

        $this->info($text, $data, $extra);
    }

    /**
     * Erros silenciosos do sistema
     *
     * @param string $message
     * @param array $data
     * @param boolean $extra
     * @return void
     */
    public function error(string $message, array $data = [], bool $extra = true): void
    {
        $logger = new Logger("error");

        $this->file = $this->path . "/error/" . date("Y-m-d") . "." . $this->config["extension"];

        $logger->pushHandler(new StreamHandler($this->file, Logger::ERROR));

        if ($extra) {
            $logger->pushProcessor(function ($record) {
                $record["extra"]["URI"] = $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
                $record["extra"]["REQUEST_METHOD"] = $_SERVER["REQUEST_METHOD"];
                return $record;
            });
        }

        $logger->error($message, $data);
    }

    public function exception(\Throwable $e, array $context = []): void
    {
        $logger = new Logger("exception");

        $this->file = $this->path . "/error/" . date("Y-m-d") . "." . $this->config["extension"];

        $logger->pushHandler(new StreamHandler($this->file, Logger::ERROR));

        $logger->pushProcessor(function ($record) {
            $record["extra"]["URI"] = $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
            $record["extra"]["REQUEST_METHOD"] = $_SERVER["REQUEST_METHOD"];
            return $record;
        });

        $logger->error($e->getMessage(), array_merge($context, [
            'exception' => get_class($e),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            'trace'     => $e->getTraceAsString(),
        ]));
    }

    private function info(string $message, array $data, bool $extra)
    {

        $logger = new Logger("app");

        $this->file = $this->path . "/app/" . date("Y-m-d") . "." . $this->config["extension"];

        $logger->pushHandler(new StreamHandler($this->file, Logger::INFO));

        if ($extra) {

            $logger->pushProcessor(function ($record) {

                // $record["extra"]["HTTP_HOST"] = $_SERVER["HTTP_HOST"];
                $record["extra"]["URI"] = $_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
                $record["extra"]["REQUEST_METHOD"] = $_SERVER["REQUEST_METHOD"];
                return $record;
            });
        }

        $logger->info($message, $data);
    }

}
