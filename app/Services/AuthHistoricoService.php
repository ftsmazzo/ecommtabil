<?php
namespace App\Services;

use App\Core\DB;

class AuthHistoricoService
{
    public static function registrar(string $tabela, array $dados): int
    {
        $ip = $_SERVER["REMOTE_ADDR"] ?? null;
        $ua = $_SERVER["HTTP_USER_AGENT"] ?? null;
        $local = null;

        if ($ip && $ip !== "::1" && $ip !== "127.0.0.1") {
            $geo = new \App\Lib\HGGeoIP();
            $location = $geo->getResults($ip);

            $local = trim(
                ($location["city"] ?? "") . " " .
                ($location["region"] ?? "") . " " .
                ($location["country_name"] ?? "")
            ) ?: null;
        }

        $sistema = $dados["sistema"] ?? null;

        if (!$sistema || $sistema === $ua || $sistema === "unknown") {
            $sistema = self::detectSistema();
        }

        $default = [
            "ip" => $ip,
            "local" => $local,
            "sistema" => $sistema,
        ];

        $insert = DB::table($tabela)->insert($dados + $default);

        return $insert->id;
    }

    private static function detectSistema(): string
    {
        if (PHP_SAPI === "cli") {
            return "CLI";
        }

        $uri = $_SERVER["REQUEST_URI"] ?? "";
        $accept = $_SERVER["HTTP_ACCEPT"] ?? "";
        $requestedWith = $_SERVER["HTTP_X_REQUESTED_WITH"] ?? "";
        $authorization = $_SERVER["HTTP_AUTHORIZATION"] ?? "";

        if (stripos($uri, "/api") === 0 || stripos($uri, "/api/") === 0 || $authorization) {
            return "API";
        }

        if (stripos($requestedWith, "xmlhttprequest") !== false) {
            return "Painel Admin (AJAX)";
        }

        if (stripos($accept, "application/json") !== false) {
            return "API";
        }

        return "Painel Admin";
    }
}
