<?php

namespace App\Lib;

use App\Core\Config;
use GuzzleHttp\Client;

class HGGeoIP
{
    private Client $client;
    private string $apiKey;
    private string $baseUrl = 'https://api.hgbrasil.com/geoip';

    // limite do plano free
    private int $dailyLimit = 400;

    // arquivo onde salvamos o contador
    private string $counterFile;

    public function __construct(?string $apiKey = null, ?Client $client = null, ?string $counterFile = null)
    {
        $this->apiKey = $apiKey ?? Config::get('hgbrasil.private_key');

        $this->client = $client ?? new Client([
            'timeout' => 5,
            'http_errors' => false,
        ]);

        $this->counterFile = $counterFile ?? __DIR__ . '/../../storage/plugins/hg_geoip_counter.json';
    }

    /**
     * Retorna os dados de localização ou null
     */
    public function getLocation(?string $ip = null): ?array
    {
        try {
            // verifica limite diário
            if (!$this->canMakeRequest()) {
                return null;
            }

            $query = [
                'key' => $this->apiKey,
            ];

            if ($ip) {
                $query['address'] = $ip;
            }

            $response = $this->client->get($this->baseUrl, [
                'query' => $query,
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = json_decode((string) $response->getBody(), true);

            if (!is_array($data)) {
                return null;
            }

            // incrementa contador apenas se a chamada foi válida
            $this->incrementCounter();

            return $data;

        } catch (\Throwable $e) {
            // qualquer erro = silêncio total
            return null;
        }
    }

    /**
     * Retorna apenas o bloco "results"
     */
    public function getResults(?string $ip = null): ?array
    {
        $data = $this->getLocation($ip);

        return $data['results'] ?? null;
    }

    /**
     * Verifica se ainda pode fazer requisições hoje
     */
    private function canMakeRequest(): bool
    {
        $data = $this->readCounter();

        return $data['count'] < $this->dailyLimit;
    }

    /**
     * Incrementa o contador diário
     */
    private function incrementCounter(): void
    {
        $data = $this->readCounter();
        $data['count']++;

        file_put_contents($this->counterFile, json_encode($data));
    }

    /**
     * Lê o contador e reseta se mudou o dia
     */
    private function readCounter(): array
    {
        $today = date('Y-m-d');

        if (!file_exists($this->counterFile)) {
            return [
                'date' => $today,
                'count' => 0,
            ];
        }

        $data = json_decode(file_get_contents($this->counterFile), true);

        if (!is_array($data) || ($data['date'] ?? null) !== $today) {
            return [
                'date' => $today,
                'count' => 0,
            ];
        }

        return $data;
    }
}
