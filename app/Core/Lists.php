<?php

namespace App\Core;

class Lists
{
    protected string $name;
    protected array $items = [];

    protected static array $config;
    protected static array $cache = [];

    protected function __construct(string $name, array $items)
    {
        $this->name  = $name;
        $this->items = $items;
    }

    protected static function config(): array
    {

        self::$config = Config::get('list');

        return self::$config;
    }

    public static function get(string $name): self
    {
        // cache em memória
        if (isset(self::$cache[$name])) {
            return self::$cache[$name];
        }

        $config = self::config();

        $file = rtrim($config['path'], '/')
              . '/'
              . $name
              . '.'
              . $config['extension'];

        if (!file_exists($file)) {
            throw new \RuntimeException("Lista '{$name}' não encontrada.");
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $items = [];

        foreach ($lines as $line) {
            $line = trim($line);

            // comentários
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, $value] = array_map('trim', explode('=', $line, 2));
                $items[$key] = $value;
            } else {
                $items[] = $line;
            }
        }

        return self::$cache[$name] = new self($name, $items);
    }

    /* =====================
       Métodos de acesso
       ===================== */

    public function toArray(): array
    {
        return $this->items;
    }

    public function all(): array
    {
        return $this->items;
    }

    public function getItem(string $key, $default = null)
    {
        return $this->items[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    public function keys(): array
    {
        return array_keys($this->items);
    }

    public function values(): array
    {
        return array_values($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function json(): string
    {
        return json_encode($this->items, JSON_UNESCAPED_UNICODE);
    }

    public function random()
    {
        return $this->items[array_rand($this->items)];
    }
}
