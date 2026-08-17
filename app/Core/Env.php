<?php
namespace App\Core;

/**
 * Loader simples de variaveis de ambiente.
 *
 * Regras:
 * - o arquivo `.env` e opcional
 * - o carregamento acontece uma unica vez
 * - valores carregados ficam disponiveis via `env()`
 * - `Config` continua sendo a fonte dos arquivos `config/*.php`
 */
class Env
{
    private static array $values = [];
    private static bool $loaded = false;

    /**
     * Carrega o arquivo `.env` do root da aplicacao.
     */
    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }

        self::$loaded = true;

        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            self::$values[$key] = self::cast($value);
        }

        foreach (self::$values as $key => $value) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            $strVal = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
            if (\function_exists('putenv')) {
                \putenv($key . '=' . $strVal);
            }
        }
    }

    /**
     * Lê uma chave do ambiente, respeitando cache interno e fallback.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$values)) {
            return self::$values[$key];
        }

        $value = $_ENV[$key] ?? $_SERVER[$key] ?? (\function_exists('getenv') ? \getenv($key) : false);

        if ($value === false || $value === null) {
            return $default;
        }

        return self::cast((string) $value);
    }

    /**
     * Converte strings comuns do .env para tipos nativos.
     */
    private static function cast(string $value): mixed
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }

        if (str_contains($value, ' #')) {
            $value = trim(explode(' #', $value, 2)[0]);
        }

        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => is_numeric($value) ? (str_contains($value, '.') ? (float) $value : (int) $value) : $value,
        };
    }
}
