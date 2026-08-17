<?php

namespace App\Core;

use Exception;

/**
 * Sistema de configuração simples e eficiente
 * Suporta acesso do tipo: Config::get('app.name')
 */
class Config
{
    private static ?string $basePath = null;
    private static array $cache = [];

    /**
     * Define o caminho base da pasta config/
     */
    public static function init(string $path): void
    {
        self::$basePath = rtrim($path, '/');
    }

    /**
     * Retorna um valor de configuração
     *
     * @param string $key Ex: "app.name" ou "database.connections.mysql"
     * @param mixed $default Valor padrão se a chave não existir
     * @return mixed
     * @throws Exception
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$basePath) {
            throw new Exception("Config path not initialized. Use Config::init() before calling get().");
        }

        $segments = explode('.', $key);
        $fileName = array_shift($segments);
        $filePath = self::$basePath . '/' . $fileName . '.php';

        if (!isset(self::$cache[$fileName])) {
            if (!file_exists($filePath)) {
                throw new Exception("Config file not found: {$filePath}");
            }

            $data = require $filePath;
            if (!is_array($data)) {
                throw new Exception("Config file must return an array: {$filePath}");
            }

            self::$cache[$fileName] = $data;
        }

        $value = self::$cache[$fileName];
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Retorna todas as configurações de um arquivo
     */
    public static function all(string $file): array
    {
        return self::get($file, []);
    }
}
