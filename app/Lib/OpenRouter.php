<?php

namespace App\Lib;

/**
 * Cliente OpenRouter — leitura de PDF via plugin file-parser (Mistral OCR por padrão).
 * @see https://openrouter.ai/docs/guides/overview/multimodal/pdfs
 */
class OpenRouter
{
    private string $apiKey;
    private string $model;
    private string $pdfEngine;
    private string $httpReferer;
    private string $appTitle;

    public function __construct(?array $conf = null)
    {
        $conf = $conf ?? \App\Core\Config::get("openrouter");
        if (!is_array($conf)) {
            $conf = [];
        }

        $this->apiKey = trim((string) ($conf["api_key"] ?? ""));
        $this->model = (string) ($conf["model"] ?? "openai/gpt-4o-mini");
        $this->pdfEngine = (string) ($conf["pdf_engine"] ?? "mistral-ocr");
        $this->httpReferer = (string) ($conf["http_referer"] ?? "");
        $this->appTitle = (string) ($conf["app_title"] ?? "E-commtabil");

        if ($this->apiKey === "") {
            throw new \RuntimeException("OPENROUTER_API_KEY não definida.");
        }
    }

    public static function disponivel(): bool
    {
        try {
            $conf = \App\Core\Config::get("openrouter");
            $key = is_array($conf) ? trim((string) ($conf["api_key"] ?? "")) : "";
            return $key !== "";
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Extrai o texto completo de um PDF local (base64) via Mistral OCR / file-parser.
     *
     * @return array{ok:bool,text?:string,error?:string,engine?:string,model?:string}
     */
    public function extrairTextoPdf(string $caminhoArquivo, string $nomeArquivo = "documento.pdf"): array
    {
        if (!is_readable($caminhoArquivo)) {
            return ["ok" => false, "error" => "Arquivo PDF ilegível."];
        }

        $bin = @file_get_contents($caminhoArquivo);
        if ($bin === false || $bin === "") {
            return ["ok" => false, "error" => "Não foi possível ler o PDF."];
        }

        $b64 = base64_encode($bin);
        $dataUrl = "data:application/pdf;base64," . $b64;
        $filename = $nomeArquivo !== "" ? $nomeArquivo : basename($caminhoArquivo);

        $payload = [
            "model" => $this->model,
            "messages" => [[
                "role" => "user",
                "content" => [
                    [
                        "type" => "text",
                        "text" => "Extraia TODO o texto deste PDF na ordem das páginas. "
                            . "Preserve datas (dd/mm/aaaa), valores monetários, nomes e identificações do extrato/comprovante. "
                            . "Não resuma. Não explique. Responda SOMENTE com o texto extraído.",
                    ],
                    [
                        "type" => "file",
                        "file" => [
                            "filename" => $filename,
                            "file_data" => $dataUrl,
                        ],
                    ],
                ],
            ]],
            "plugins" => [[
                "id" => "file-parser",
                "pdf" => [
                    "engine" => $this->pdfEngine,
                ],
            ]],
        ];

        $resp = $this->postChat($payload);
        if (!$resp["ok"]) {
            return $resp;
        }

        $text = $this->textoDaResposta($resp["raw"] ?? []);
        if ($text === "") {
            return [
                "ok"    => false,
                "error" => "OpenRouter retornou resposta sem texto.",
                "engine"=> $this->pdfEngine,
                "model" => $this->model,
            ];
        }

        return [
            "ok"     => true,
            "text"   => $text,
            "engine" => $this->pdfEngine,
            "model"  => $this->model,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{ok:bool,raw?:array,error?:string}
     */
    private function postChat(array $payload): array
    {
        $headers = [
            "Authorization: Bearer " . $this->apiKey,
            "Content-Type: application/json",
        ];
        if ($this->httpReferer !== "") {
            $headers[] = "HTTP-Referer: " . $this->httpReferer;
        }
        if ($this->appTitle !== "") {
            $headers[] = "X-Title: " . $this->appTitle;
        }

        $ch = curl_init("https://openrouter.ai/api/v1/chat/completions");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT        => 180,
            CURLOPT_CONNECTTIMEOUT => 30,
        ]);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            return ["ok" => false, "error" => "cURL OpenRouter: " . $err];
        }

        $json = json_decode((string) $body, true);
        if (!is_array($json)) {
            return ["ok" => false, "error" => "Resposta inválida do OpenRouter (HTTP {$code})."];
        }

        if ($code >= 400) {
            $msg = (string) ($json["error"]["message"] ?? $json["error"] ?? "HTTP {$code}");
            return ["ok" => false, "error" => "OpenRouter: " . $msg];
        }

        return ["ok" => true, "raw" => $json];
    }

    /**
     * @param array<string,mixed> $raw
     */
    private function textoDaResposta(array $raw): string
    {
        $content = $raw["choices"][0]["message"]["content"] ?? null;
        if (is_string($content)) {
            return trim($content);
        }
        if (is_array($content)) {
            $parts = [];
            foreach ($content as $chunk) {
                if (is_string($chunk)) {
                    $parts[] = $chunk;
                } elseif (is_array($chunk) && ($chunk["type"] ?? "") === "text") {
                    $parts[] = (string) ($chunk["text"] ?? "");
                }
            }
            return trim(implode("\n", $parts));
        }
        return "";
    }
}
