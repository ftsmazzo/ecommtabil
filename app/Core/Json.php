<?php
namespace App\Core;

class Json
{

    public static function encode($data): string
    {
        return self::safeJson($data);
    }

    public static function decode($json): array
    {
        return self::fromSafeJson($json);
    }

    public static function safeJson($data): string
    {
        if (is_array($data)) {
            // Remove apenas valores nulos (mas mantém índices)
            $data = array_filter($data, fn($v) => $v !== null);
        }

        $json = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Erro ao codificar JSON: ' . json_last_error_msg());
        }

        return $json;
    }

    public static function fromSafeJson(?string $json): array
    {

        if (!$json) return [];

        $json = trim($json);
        $json = preg_replace('/^\xEF\xBB\xBF/', '', $json); // BOM UTF-8

        $data = json_decode($json, true);

        // ✅ Se veio como string (JSON de string), tenta decodificar novamente
        if (json_last_error() === JSON_ERROR_NONE && is_string($data)) {
            $data2 = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data2)) {
                return $data2;
            }
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            $json2 = trim($json);

            if ((str_starts_with($json2, '"') && str_ends_with($json2, '"')) ||
                (str_starts_with($json2, "'") && str_ends_with($json2, "'"))) {
                $json2 = substr($json2, 1, -1);
            }

            if (str_ends_with($json2, '"') && str_starts_with($json2, '[')) {
                $json2 = rtrim($json2, '"');
            }

            $json2 = stripcslashes($json2);

            $data = json_decode($json2, true);

            // ✅ mesma regra aqui também
            if (json_last_error() === JSON_ERROR_NONE && is_string($data)) {
                $data2 = json_decode($data, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($data2)) {
                    return $data2;
                }
            }

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [];
            }
        }

        return is_array($data) ? $data : [];
    }



    public static function sortAndReindex(array $data): array
    {
        // Ordena por valor (mantém relação chave/valor até aqui)
        asort($data, SORT_NATURAL | SORT_FLAG_CASE);

        // Recria as chaves numéricas a partir de 1
        $reindexed = [];
        $i = 1;
        foreach ($data as $value) {
            $reindexed[$i++] = $value;
        }

        return $reindexed;
    }

}
