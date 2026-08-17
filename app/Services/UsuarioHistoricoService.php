<?php

namespace App\Services;

use App\Models\UsuarioHistorico;

class UsuarioHistoricoService
{
    public static function registrar(array $dados): int
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $local = null;

        if ($ip && $ip !== '::1' && $ip !== '127.0.0.1') {
            $geo = new \App\Lib\HGGeoIP();
            $location = $geo->getResults($ip);

            $local = trim(
                ($location['city'] ?? '') . ' ' .
                ($location['region'] ?? '') . ' ' .
                ($location['country_name'] ?? '')
            ) ?: null;
        }

        $sistema = $dados['sistema'] ?? null;

        if (!$sistema || $sistema === $ua || $sistema === 'unknown') {
            $sistema = self::detectSistema();
        }

        $payload = array_merge([
            'ip' => $ip,
            'local' => $local,
            'sistema' => $sistema,
        ], $dados);

        $insert = UsuarioHistorico::create($payload);

        return $insert->id;
    }

    private static function detectSistema(): string
    {
        if (PHP_SAPI === 'cli') {
            return 'CLI';
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (stripos($uri, '/api') === 0 || stripos($uri, '/api/') === 0 || $authorization) {
            return 'API';
        }

        if (stripos($requestedWith, 'xmlhttprequest') !== false) {
            return 'Painel Admin (AJAX)';
        }

        if (stripos($accept, 'application/json') !== false) {
            return 'API';
        }

        return 'Painel Admin';
    }
}
