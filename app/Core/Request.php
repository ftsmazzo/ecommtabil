<?php

namespace App\Core;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class Request
{
    protected Client $http;

    /**
     * Dados filtrados vindos do Router
     */
    protected array $data = [];

    public function __construct(?array $data = null)
    {
        $this->http = new Client(
            [
                "timeout" => 10,
                "verify" => false
            ]
        );

        // Se o Router enviar dados processados, guarda aqui.
        if ($data !== null) {
            $this->data = $data;
        }
    }

    // -----------------------------------------
    // Dados da requisição do usuário
    // -----------------------------------------

    public function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public function uri(): string
    {
        return explode('?', $_SERVER['REQUEST_URI'])[0] ?? '/';
    }

    public function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function headers(): array
    {
        return getallheaders() ?: [];
    }

    public function header(string $key, $default = null): ?string
    {
        $h = $this->headers();
        return $h[$key] ?? $default;
    }

    /**
     * Pega um único campo na ordem:
     * 1) Dados enviados pelo Router
     * 2) $_POST
     * 3) $_GET
     */
    public function input(string $key, $default = null)
    {
        if (!empty($this->data) && array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }

        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    /**
     * Retorna dados do Router se existirem.
     * Senão retorna GET+POST normais.
     */
    public function all(): array
    {
        if (!empty($this->data)) {
            return $this->data;
        }

        return array_merge($_GET, $_POST);
    }

    /**
     * Retorna JSON cru do body
     */
    public function json(): array
    {
        $input = file_get_contents("php://input");
        $decoded = json_decode($input, true);
        return $decoded ?: [];
    }

    /**
     * Arquivos enviados
     */
    public function file(string $key)
    {
        return $_FILES[$key] ?? null;
    }

    // -----------------------------------------
    // Guzzle — requisições HTTP externas
    // -----------------------------------------

    public function get(string $url, array $options = []): array|string|null
    {
        try {
            $res = $this->http->get($url, $options);
            return $this->parseResponse($res->getBody()->getContents());
        } catch (RequestException $e) {
            return $this->handleException($e);
        }
    }

    public function post(string $url, array $options = []): array|string|null
    {
        try {
            $res = $this->http->post($url, $options);
            return $this->parseResponse($res->getBody()->getContents());
        } catch (RequestException $e) {
            return $this->handleException($e);
        }
    }

    public function put(string $url, array $options = []): array|string|null
    {
        try {
            $res = $this->http->put($url, $options);
            return $this->parseResponse($res->getBody()->getContents());
        } catch (RequestException $e) {
            return $this->handleException($e);
        }
    }

    public function delete(string $url, array $options = []): array|string|null
    {
        try {
            $res = $this->http->delete($url, $options);
            return $this->parseResponse($res->getBody()->getContents());
        } catch (RequestException $e) {
            return $this->handleException($e);
        }
    }

    private function parseResponse(string $body)
    {
        $json = json_decode($body, true);
        return $json !== null ? $json : $body;
    }

    private function handleException(RequestException $e)
    {
        if ($e->hasResponse()) {
            $body = (string) $e->getResponse()->getBody();
            return $this->parseResponse($body);
        }
        return null;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function appendData(array $data): void
    {
        $this->data = array_merge($this->data, $data);
    }
}
