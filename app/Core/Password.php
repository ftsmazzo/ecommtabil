<?php
namespace App\Core;

use App\Core\Config;

/**
 * Classe responsável por manipular senhas e utilitários de criptografia.
 *
 * - Fornece funções para gerar, hashear e verificar senhas.
 * - Implementa verificação de necessidade de rehash.
 * - Inclui métodos simples de (pseudo)criptografia simétrica para uso não crítico.
 *
 * @package App\Core
 */
class Password
{
    /** @var array Configurações carregadas do arquivo config/passwd.php */
    private static $config;

    /**
     * Construtor: carrega as configurações de hashing.
     */
    public function __construct()
    {
        self::$config = Config::get("passwd");
    }

    /**
     * Gera um hash seguro para a senha informada.
     *
     * @param string $password Senha original.
     * @return string Hash da senha.
     */
    public static function hash(string $password): string
    {
        new self();
        return password_hash($password, self::$config["algo"], self::$config["option"]);
    }

    /**
     * Verifica se a senha informada corresponde ao hash armazenado.
     *
     * @param string $password Senha em texto puro.
     * @param string $hash Hash armazenado.
     * @return bool Verdadeiro se a senha for válida.
     */
    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Verifica se o hash precisa ser reprocessado com novas opções.
     *
     * @param string $hash Hash atual.
     * @return bool Verdadeiro se o hash precisar ser regenerado.
     */
    public static function rehash(string $hash): bool
    {
        new self();
        return password_needs_rehash($hash, self::$config["algo"], self::$config["option"]);
    }

    /**
     * Gera uma senha aleatória e seu respectivo hash.
     *
     * @param int $length Tamanho desejado da senha.
     * @param string|null $upper Letras maiúsculas personalizadas (opcional).
     * @param string|null $lower Letras minúsculas personalizadas (opcional).
     * @param string|null $number Dígitos numéricos personalizados (opcional).
     * @param string|null $symbol Símbolos personalizados (opcional).
     * @return array [senha_gerada, hash_da_senha]
     */
    public static function generate(int $length, ?string $upper = null, ?string $lower = null, ?string $number = null, ?string $symbol = null): array
    {
        $upper  = $upper  ?? "ABCDEFGHIJKLMNOPQRSTUVYXWZ";
        $lower  = $lower  ?? "abcdefghijklmnopqrstuvyxwz";
        $number = $number ?? "0123456789";
        $symbol = $symbol ?? "!@#$%&*";

        $pool = str_shuffle($upper . $lower . $number . $symbol);
        $password = substr(str_shuffle($pool), 0, $length);

        return [$password, self::hash($password)];
    }

    /**
     * Realiza uma pseudo-criptografia simples (não segura para dados sensíveis).
     *
     * ⚠️ Uso recomendado apenas para ofuscação ou tokens de curta duração.
     *
     * @param string $value Valor a ser ofuscado.
     * @param string $sep Separador interno (padrão: "$").
     * @return string Valor ofuscado.
     */
    public static function encrypt(string $value, string $sep = "$"): string
    {
        $chaves = ['acefwhjlmnpqstuwxy123456789', 'acdfhiklmnvprtuvwxz123456789'];
        $chave = $chaves[array_rand($chaves)];
        $value64 = rtrim(strtr(base64_encode($value), '+/', '-_'), '=');

        $partes = [];
        for ($i = 0; $i < 4; $i++) {
            $partes[] = substr(str_shuffle($chave), 0, rand(1, strlen($chave)));
            $partes[] = strrev(substr($value64, ceil(strlen($value64) / 4) * $i, ceil(strlen($value64) / 4)));
        }

        return implode($sep, $partes) . $sep . substr(str_shuffle($chave), 0, rand(1, strlen($chave)));
    }

    /**
     * Desofusca o valor gerado pelo método encrypt().
     *
     * @param string $value Valor ofuscado.
     * @param string $sep Separador usado na ofuscação.
     * @return string Valor original.
     */
    public static function decrypt(string $value, string $sep = "$"): string
    {
        $parts  = explode($sep, $value);
        if (count($parts) < 8) {
            return '';
        }

        $base64 = strrev($parts[7]) . strrev($parts[5]) . strrev($parts[3]) . strrev($parts[1]);
        return base64_decode(str_pad(strtr($base64, '-_', '+/'), strlen($base64) % 4, '=', STR_PAD_RIGHT));
    }
}