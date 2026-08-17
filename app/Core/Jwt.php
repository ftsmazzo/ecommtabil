<?php
namespace App\Core;

use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Exception;

class Jwt
{
    private string $key;
    private string $alg;
    private int $expires;
    private int $leeway;
    private ?string $issuer;
    private ?string $audience;

    public function __construct()
    {
        $config = Config::get("jwt");

        $this->key = $config['secret_key'] ?? 'x460bMIeV4qJZiA3gR9c85R1YOiF7GUISnzf2xbBp28SDwEFDfdyJ66a5CAUkM6l';
        $this->alg = $config['alg'] ?? 'HS256';
        $this->expires = $config['expires_at'] ?? 3600; // 1h
        $this->leeway = $config['leeway'] ?? 60; // tolerância de 1 min
        $this->issuer = $config['issuer'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $this->audience = $config['audience'] ?? null;

        // Permite um pequeno desvio de horário entre servidores
        FirebaseJWT::$leeway = $this->leeway;
    }

    /**
     * Gera um token JWT com claims personalizados
     */
    public function generate(array $data): string
    {
        $now = time();

        $payload = [
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->expires,
            'data' => $data
        ];

        return FirebaseJWT::encode($payload, $this->key, $this->alg);
    }

    /**
     * Decodifica um token JWT e retorna o payload completo
     */
    public function decode(string $token): ?object
    {
        try {
            return FirebaseJWT::decode($token, new Key($this->key, $this->alg));
        } catch (ExpiredException $e) {
            throw new Exception('Token expirado.');
        } catch (SignatureInvalidException $e) {
            throw new Exception('Assinatura inválida.');
        } catch (Exception $e) {
            throw new Exception('Token inválido ou malformado.');
        }
    }

    /**
     * Verifica se o token é válido e não expirou
     */
    public function validate(?string $token): bool
    {
        if (!$token) {
            return false;
        }

        try {
            $this->decode($token);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Retorna apenas os dados (payload->data)
     */
    public function getData(string $token): ?object
    {
        try {
            $decoded = $this->decode($token);
            return $decoded->data ?? null;
        } catch (Exception $e) {
            return null;
        }
    }

    // --- Métodos estáticos de conveniência ---

    public static function make(array $data): string
    {
        return (new self())->generate($data);
    }

    public static function verify(string $token): bool
    {
        return (new self())->validate($token);
    }

    public function getExpires(): int
    {
        return $this->expires;
    }
}
