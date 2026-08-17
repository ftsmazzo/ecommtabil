<?php

namespace App\Lib;

use App\Core\Config;
use Exception;
use CURLFile;

class Zaps
{
    private string $apiUrl = 'https://zaps.app.br:443/backend/api/messages/send';
    private string $token;

    public function __construct()
    {
        $this->token = Config::get('whatsapp.token');
    }

    public function setToken(string $token): self
    {
        $this->token = $token;
        return $this;
    }

    public function sendText(string $number, string $message, bool $saveOnTicket = false, bool $startChatbot = false, bool $linkPreview = true): array
    {
        $payload = [
            'number' => $this->formatNumber($number),
            'body' => $message,
            'saveOnTicket' => $saveOnTicket,
            'linkPreview' => $linkPreview,
            'startChatbot' => $startChatbot,
        ];

        return $this->requestJson($payload);
    }

    public function sendMedia(string $number, array $mediaPaths, ?string $message = null, $saveOnTicket = false, bool $startChatbot = false): array
    {
        $fields = [
            'number' => $this->formatNumber($number),
            'saveOnTicket' => $saveOnTicket,
            'startChatbot' => $startChatbot ? 'true' : 'false',
        ];

        if ($message) {
            $fields['body'] = $message;
        }

        foreach ($mediaPaths as $i => $path) {
            if (!file_exists($path)) {
                throw new Exception("Arquivo não encontrado: {$path}");
            }
            $fields["medias[$i]"] = new CURLFile($path);
        }

        return $this->requestMultipart($fields);
    }

    private function requestJson(array $payload): array
    {
        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Erro CURL: $error");
        }

        return json_decode($response, true) ?: ['raw' => $response];
    }

    private function requestMultipart(array $fields): array
    {
        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token,
            ],
            CURLOPT_POSTFIELDS => $fields,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Erro CURL: $error");
        }

        return json_decode($response, true) ?: ['raw' => $response];
    }

    private function formatNumber(string $number): string
    {
        // Caso seja um grupo (JID), não altera nada
        if (str_contains($number, '@g.us')) {
            return $number;
        }

        // Remove tudo que não for número
        $number = preg_replace('/\D/', '', $number ?? '');

        if (empty($number)) {
            throw new Exception('Número de WhatsApp inválido: campo vazio ou formato incorreto.');
        }

        // Adiciona código do país (55) se não tiver
        if (!str_starts_with($number, '55')) {
            $number = '55' . $number;
        }

        // Valida tamanho (mínimo 12, máximo 14 dígitos, incluindo o código do país)
        $length = strlen($number);
        if ($length < 12 || $length > 14) {
            throw new Exception("Número de WhatsApp inválido: {$number} ({$length} dígitos). Deve ter entre 10 e 13 dígitos após o código do país.");
        }

        return $number;
    }
}
