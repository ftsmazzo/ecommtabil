<?php
namespace App\Core;

use App\Core\Config;

/**
 * Classe Cookie - gerenciamento seguro de cookies HTTP.
 *
 * - Totalmente compatível com a versão anterior.
 * - Inclui parâmetros de segurança (Secure, HttpOnly, SameSite).
 * - Usada diretamente ou integrada à classe Session.
 *
 * @package App\Core
 */
class Cookie
{
    /** @var int Tempo de vida em dias */
    protected $time;

    /** @var string Caminho padrão dos cookies */
    protected $path;

    /**
     * Construtor: carrega as configurações.
     */
    public function __construct()
    {
        $config = Config::get("session.cookie");

        $this->time = (int) ($config["days"] ?? 7);
        $this->path = $config["path"] ?? '/';
    }

    /**
     * Retorna o valor de um cookie.
     */
    public function __get($name)
    {
        return $_COOKIE[$name] ?? null;
    }

    /**
     * Verifica se um cookie existe.
     */
    public function __isset($name): bool
    {
        return isset($_COOKIE[$name]);
    }

    /**
     * Retorna todos os cookies.
     */
    public function all(): object
    {
        return (object) $_COOKIE;
    }

    /**
     * Define um cookie seguro.
     *
     * @param string $name
     * @param string $value
     * @param int|null $days
     * @return Cookie
     */
    // public function set(string $name, $value, ?int $days = null): Cookie
    // {
    //     $days = $days ?? $this->time;
    //     setcookie($name, $value, [
    //         'expires'  => time() + (86400 * $days),
    //         'path'     => $this->path,
    //         'secure'   => isset($_SERVER['HTTPS']),
    //         'httponly' => true,
    //         'samesite' => 'Lax'
    //     ]);

    //     $_COOKIE[$name] = $value; // garante leitura imediata
    //     return $this;
    // }

    public function set(string $name, $value, ?int $days = null, array $opts = []): Cookie
    {
        $days = $days ?? $this->time;

        $secure = $opts['secure'] ?? (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $sameSite = $opts['samesite'] ?? 'Lax';
        $path = $opts['path'] ?? $this->path;

        // permite expiração por seconds (prioridade)
        if (isset($opts['seconds'])) {
            $expires = time() + (int)$opts['seconds'];
        } else {
            $expires = time() + (86400 * $days);
        }

        setcookie($name, $value, [
            'expires'  => $expires,
            'path'     => $path,
            'secure'   => (bool)$secure,
            'httponly' => true,
            'samesite' => $sameSite,
        ]);

        $_COOKIE[$name] = $value;
        return $this;
    }

    /**
     * Exclui um cookie.
     */
    public function unset(string $name): Cookie
    {
        setcookie($name, '', [
            'expires'  => time() - 3600,
            'path'     => $this->path,
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        unset($_COOKIE[$name]);
        return $this;
    }

    /**
     * Verifica se o cookie existe.
     */
    public function has(string $key): bool
    {
        return isset($_COOKIE[$key]);
    }
}
