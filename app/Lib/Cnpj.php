<?php
declare(strict_types=1);

namespace App\Lib;

final class Cnpj
{
    private string $urlBase = 'https://www.receitaws.com.br/v1/cnpj/';
    private ?string $cnpj = null;

    public function getCnpj(): ?string
    {
        return $this->cnpj;
    }

    public function setCnpj(string $cnpj): self
    {
        $cnpj = preg_replace('/\D+/', '', $cnpj) ?? '';

        if (strlen($cnpj) !== 14) {
            throw new \InvalidArgumentException('CNPJ deve conter 14 dígitos.');
        }

        // Opcional: validar dígitos verificadores
        if (!$this->isValidCnpj($cnpj)) {
            throw new \InvalidArgumentException('CNPJ inválido.');
        }

        $this->cnpj = $cnpj;
        return $this;
    }

    /**
     * Retorna array decodificado (recomendado).
     */
    public function get(string $cnpj): array
    {
        $this->setCnpj($cnpj);

        $url = $this->urlBase . $this->cnpj;
        $result = $this->requestJson($url);

        return $result;
    }

    private function requestJson(string $url): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Falha ao inicializar cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'User-Agent: AppCnpjClient/1.0',
            ],
        ]);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new \RuntimeException("Erro cURL ({$errno}): {$error}");
        }

        if ($body === false || $body === '') {
            throw new \RuntimeException('Resposta vazia da API.');
        }

        // Tratar status HTTP relevantes
        if ($httpCode === 429) {
            throw new \RuntimeException('Rate limit excedido (429).');
        }
        if ($httpCode === 504) {
            throw new \RuntimeException('Timeout da API (504).');
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException("HTTP {$httpCode} retornado pela API.");
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new \RuntimeException('JSON inválido retornado pela API.');
        }

        // Algumas APIs retornam status/erro no payload
        if (($data['status'] ?? null) !== 'OK') {
            $msg = $data['message'] ?? 'Consulta não retornou OK.';
            throw new \RuntimeException($msg);
        }

        return $data;
    }

    private function isValidCnpj(string $cnpj): bool
    {
        $cnpj = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $cnpj));

        if (strlen($cnpj) !== 14 || preg_match('/^(.)\\1{13}$/', $cnpj)) {
            return false;
        }

        $calc = function (string $base, array $weights): ?int {
            $sum = 0;
            foreach ($weights as $i => $w) {
                $char = $base[$i] ?? '';

                if (ctype_digit($char)) {
                    $value = (int) $char;
                } elseif (ctype_alpha($char)) {
                    $value = ord($char) - 48;
                } else {
                    return null;
                }

                $sum += $value * $w;
            }
            $mod = $sum % 11;
            return ($mod < 2) ? 0 : (11 - $mod);
        };

        $w1 = [5,4,3,2,9,8,7,6,5,4,3,2];
        $w2 = [6,5,4,3,2,9,8,7,6,5,4,3,2];

        $d1 = $calc(substr($cnpj, 0, 12), $w1);
        if ($d1 === null) {
            return false;
        }

        $d2 = $calc(substr($cnpj, 0, 12) . $d1, $w2);
        if ($d2 === null) {
            return false;
        }

        return $cnpj[12] === (string)$d1 && $cnpj[13] === (string)$d2;
    }
}
