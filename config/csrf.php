<?php
return [

    /**
     * 🔒 Ativa ou desativa o uso de CSRF
     * Se "false", o Csrf::generate() retorna null e o Csrf::confirm() sempre passa.
     */
    "secure" => true,

    /**
     * Nome da chave que armazena o array de tokens na sessão.
     * Você pode manter o mesmo nome antigo, pois agora ele armazena múltiplos tokens.
     */
    "hash_name" => "csrf_tokens",

    /**
     * Algoritmo HMAC a ser usado para assinar os tokens.
     * Lista de opções: https://www.php.net/manual/pt_BR/function.hash-hmac-algos.php
     */
    "algo" => "sha256",

    /**
     * Frase base (message) usada como parte do HMAC.
     * Pode ser algo fixo e identificável para sua aplicação.
     */
    "data" => "csrf secure",

    /**
     * Chave secreta usada no HMAC.
     * ⚠️ Gere uma vez e mantenha fixa entre deploys.
     * Se ela mudar, todos os tokens existentes se tornam inválidos.
     */
    "key" => "dcf_csrf_secret_key_2025", // <-- substitua por uma chave fixa segura

    /**
     * Define o formato de saída do hash_hmac.
     * FALSE = hex string (padrão), TRUE = raw binary
     */
    "output" => false,

    /**
     * Número máximo de tokens válidos simultâneos na sessão.
     * Isso evita invalidação ao abrir várias abas.
     */
    "max_tokens" => 5,
];
