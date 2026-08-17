<?php
namespace App\Core;

use Exception;
use \App\Core\Config;

class FTP
{

    /** @var Config */
    protected $config;

    protected $conn_id;

    protected $mode;

    public function __construct()
    {

        $this->config = Config::get("ftp");

        if (!isset($this->config["host"]) || !isset($this->config["username"])) {

            die("Você deve inserir as informações de conexão do FTP");
        }

        try {

            $this->conn_id = ftp_connect($this->config["host"]);

        } catch (Exception $e) {

            exit($e->getMessage());
        }

        ftp_login($this->conn_id, $this->config["username"], $this->config["password"]);

        $this->mode = FTP_ASCII;

        return $this;
    }

    public function setPassive($bool)
    {
        ftp_pasv($this->conn_id, $bool);
        return $this;
    }

    public function setMode($mode)
    {
        $this->mode = $mode;
        return $this;
    }

    public function close(): FTP
    {
        ftp_close($this->conn_id);
        return $this;
    }

    public function put($from, $to, $close=true):bool
    {

        if (is_resource($from))
            $upload = ftp_fput($this->conn_id, $to, $from, $this->mode);
        else
            $upload = ftp_put($this->conn_id, $to, $from, $this->mode);

        if ($close)
            $this->close();

        return $upload ? true : false;
    }

    public function upload($from, $to, $close=true):bool
    {
        return $this->put($from, $to, $close);
    }

    public function delete($path)
    {
        $delete = ftp_delete($this->conn_id, $path);
        return $delete ? true : false;
    }

    public function createDir($dir):bool
    {

        try {

            ftp_mkdir($this->conn_id, $dir);
            return true;

        } catch (Exception $e) {

            exit($e->getMessage());
        }

        $this->close();
    }

    public function removeDir($dir):bool
    {

        try {

            ftp_rmdir($this->conn_id, $dir);
            return true;

        } catch (Exception $e) {

            exit($e->getMessage());
        }

        $this->close();
    }


}