<?php
namespace App\Core;

use Exception;

class Recaptcha
{
    private $auth;
    private $version;
    private $url;
    private $client;
    private $secret;
    private $ip;
    private $score;
    private $lastError = [];

    public function __construct()
    {
        $config = Config::get("recaptcha");

        $this->auth = $config["auth"] ?? false;
        $this->version = $config["version"] ?? 3;
        $this->client = $config["site_key"] ?? "";
        $this->secret = $config["secret_key"] ?? "";
        $this->score = $config["score"] ?? 0.5; // Valor padrão para v3
        $this->url = "https://www.google.com/recaptcha/api/siteverify";
        $this->ip = $config["ip"] ?? "";
    }

    public function setAuth($auth)
    {
        $this->auth = $auth;
    }

    public function setClientKey($client_key)
    {
        $this->client = $client_key;
    }

    public function setVersion($version)
    {
        $this->version = $version;
    }

    public function setSecretKey($secret_key)
    {
        $this->secret = $secret_key;
    }

    public function setScore($score)
    {
        $this->score = $score;
    }

    public function setIp($ip)
    {
        $this->ip = $ip;
    }

    public function validate($recaptcha, $score = null): bool
    {
        if ($this->auth) {

            $minScore = $score ?? $this->score;
            $response = $this->request($recaptcha);

            if (!isset($response["success"]) || !$response["success"]) {
                $this->lastError = $response["error-codes"] ?? ["unknown"];
                return false;
            }

            // Lógica específica para v3, onde o score é verificado
            if ($this->version == 3) {
                if (!isset($response["score"]) || $response["score"] < $minScore) {
                    $this->lastError = ["score-too-low", "score=" . ($response["score"] ?? "null"), "min=" . $minScore];
                    return false;
                }
            }
        }

        return true;
    }

    public function getLastError(): array
    {
        return $this->lastError ?? [];
    }

    private function request($token)
    {
        if (!$this->client) {
            throw new Exception("A chave \"client\" é obrigatória");
        }

        if (!$this->secret) {
            throw new Exception("A chave \"secret\" é obrigatória");
        }

        $data = [
            'secret' => $this->secret,
            'response' => $token
        ];

        if ($this->ip) {
            $data['remoteip'] = $this->ip;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout configurável

        // A validacao do token acontece contra um endpoint sensivel do Google.
        // Mantemos a verificacao TLS ativa para evitar downgrade de seguranca.
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);

        if ($response === false) {
            throw new Exception("Erro ao executar requisição cURL: " . curl_error($ch));
        }

        curl_close($ch);

        $arrResponse = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Erro ao decodificar a resposta JSON: " . json_last_error_msg());
        }

        return $arrResponse;
    }
}
