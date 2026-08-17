<?php
namespace App\Core;

use App\Core\Config;
use App\Core\Cookie;

/**
 * Classe Session aprimorada e integrada com Cookie.
 *
 * - Retrocompatível com versões anteriores.
 * - Usa configurações unificadas (config/session.php).
 * - Pode operar em conjunto com o Cookie para remember-login ou tracking.
 *
 * @package App\Core
 */
class Session
{
    /** @var array Configurações da sessão */
    private $config;

    /** @var Cookie Instância do gerenciador de cookies */
    private $cookie;

    /**
     * Construtor - inicia sessão e integra cookies.
     */
    public function __construct()
    {
        $this->config = Config::get("session");
        $this->cookie = new Cookie();

        // Configura diretório de sessão, se necessário
        if (!session_id()) {
            if (!empty($this->config["driver"]) && $this->config["driver"] === "file" && !empty($this->config["path"])) {
                session_save_path($this->config["path"]);
            }

            // Define lifetime da sessão (em minutos)
            if (!empty($this->config["lifetime"])) {
                ini_set('session.gc_maxlifetime', (int)$this->config["lifetime"] * 60);
            }

            // Inicia sessão
            session_start();

            // Protege contra fixação de ID (opcional)
            if (!empty($this->config["regenerate_on_auth"])) {
                $this->regenerate();
            }
        }
    }

    /**
     * Retorna o valor de uma variável de sessão.
     */
    public function __get(string $name)
    {
        return $_SESSION[$name] ?? null;
    }

    /**
     * Retorna o valor de uma chave da sessão (compatibilidade com get()).
     *
     * @param string $key
     * @param mixed $default Valor padrão caso a chave não exista
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Verifica se a variável existe na sessão.
     */
    public function __isset(string $name): bool
    {
        return isset($_SESSION[$name]);
    }

    /**
     * Retorna todos os valores da sessão.
     */
    public function all(): object
    {
        return (object) $_SESSION;
    }

    /**
     * Define um valor na sessão.
     *
     * @param string $key
     * @param mixed $value
     * @return Session
     */
    public function set(string $key, $value): Session
    {
        $_SESSION[$key] = is_array($value) ? (object) $value : $value;
        return $this;
    }

    /**
     * Remove um valor da sessão.
     */
    public function unset(string $key): Session
    {
        unset($_SESSION[$key]);
        return $this;
    }

    /**
     * Alias para unset().
     */
    public function del(string $key)
    {
        $this->unset($key);
    }

    /**
     * Verifica se uma chave existe na sessão.
     */
    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Regenera o ID da sessão para evitar ataques de fixação.
     */
    public function regenerate(): Session
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        return $this;
    }

    /**
     * Destroi a sessão e o cookie associado (totalmente limpo).
     */
    public function destroy(): Session
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];

            // Apaga o cookie da sessão, se existir
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax'
            ]);

            session_destroy();
        }

        return $this;
    }

    /**
     * Cria um cookie vinculado à sessão (ex: remember_token).
     *
     * @param string $name Nome do cookie.
     * @param string $value Valor armazenado.
     * @param int|null $days Tempo de vida (dias).
     * @return Session
     */
    public function rememberCookie(string $name, string $value, ?int $days = null): Session
    {
        $days = $days ?? ($this->config['cookie']['days'] ?? 7);
        $this->cookie->set($name, $value, $days);
        return $this;
    }

    /**
     * Lê um cookie vinculado à sessão.
     *
     * @param string $name Nome do cookie.
     * @return string|null Valor armazenado ou null.
     */
    public function getRememberCookie(string $name): ?string
    {
        return $this->cookie->$name ?? null;
    }

    /**
     * Remove um cookie vinculado à sessão.
     *
     * @param string $name Nome do cookie.
     * @return Session
     */
    public function forgetCookie(string $name): Session
    {
        $this->cookie->unset($name);
        return $this;
    }

    /**
     * Exibe todos os dados da sessão (debug).
     */
    public function print(): void
    {
        echo "<pre>", print_r($_SESSION, true), "</pre>";
    }
}
