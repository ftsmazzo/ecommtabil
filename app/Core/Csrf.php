<?php
namespace App\Core;

use App\Core\Session;
use App\Core\Config;

class Csrf
{
    /** @var Session */
    private static $session;

    /** @var array */
    private static $config;

    /** @var string */
    private static $sessionKey = 'csrf_tokens'; // chave da sessão que guarda a lista

    /** @var int */
    private static $maxTokens = 5; // número máximo de tokens válidos em paralelo

    public function __construct()
    {
        self::$session = new Session();
        self::$config  = Config::get("csrf");
    }

    /**
     * Gera um novo token e mantém histórico de até N tokens
     */
    public static function generate(): string
    {
        new self;

        $key  = self::$config['key'] ?? 'default_csrf_key';
        $algo = self::$config['algo'] ?? 'sha256';

        $token = bin2hex(random_bytes(32));
        $hash  = hash_hmac($algo, $token, $key);

        // pega tokens existentes
        $tokens = self::$session->get(self::$sessionKey) ?? [];

        // 🔧 garante que é array
        if ($tokens instanceof \stdClass) {
            $tokens = (array) $tokens;
        }

        // adiciona novo e mantém limite
        array_unshift($tokens, $hash);
        $tokens = array_slice($tokens, 0, self::$maxTokens);

        self::$session->set(self::$sessionKey, $tokens);

        return $hash;
    }

    /**
     * Confirma se o token é válido (entre os últimos gerados)
     */
    public static function confirm(?string $token): bool
    {
        new self;

        if (empty($token)) {
            return false;
        }

        $tokens = self::$session->get(self::$sessionKey) ?? [];

        foreach ($tokens as $valid) {
            if (hash_equals($valid, $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Limpa todos os tokens (ex: no logout)
     */
    public static function clear(): void
    {
        new self;
        self::$session->unset(self::$sessionKey);
    }
}