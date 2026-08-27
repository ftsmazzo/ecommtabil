<?php

namespace App\Services\Caixa;

/**
 * Extrai texto de PDFs digitais (extrato/comprovante Itaú).
 */
class PdfTextExtractor
{
    public function extract(string $path): string
    {
        if (!is_readable($path)) {
            return "";
        }

        if (class_exists(\Smalot\PdfParser\Parser::class)) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($path);
                $text = trim((string) $pdf->getText());
                if ($text !== "") {
                    return $this->normalizar($text);
                }
            } catch (\Throwable) {
                // fallback abaixo
            }
        }

        return $this->extrairFallback($path);
    }

    private function extrairFallback(string $path): string
    {
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === "") {
            return "";
        }

        $out = [];
        if (preg_match_all('/\((?:\\\\.|[^\\\\)]){2,}\)/s', $raw, $m)) {
            foreach ($m[0] as $chunk) {
                $s = substr($chunk, 1, -1);
                $s = str_replace(["\\n", "\\r", "\\t", "\\(", "\\)", "\\"], [" ", " ", " ", "(", ")", ""], $s);
                if (preg_match('/[\p{L}\p{N}]{2,}/u', $s)) {
                    $out[] = $s;
                }
            }
        }

        $texto = $this->normalizar(implode("\n", $out));
        if ($texto !== "") {
            return $texto;
        }

        // Stream UTF-16 / texto solto em PDFs bancários
        if (preg_match_all('/[\x20-\x7E\xC0-\xFF]{4,}/', $raw, $m2)) {
            return $this->normalizar(implode("\n", array_slice($m2[0], 0, 5000)));
        }

        return "";
    }

    private function normalizar(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/u', " ", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        if (!mb_check_encoding($text, "UTF-8")) {
            $conv = @mb_convert_encoding($text, "UTF-8", "Windows-1252,ISO-8859-1,UTF-8");
            if (is_string($conv)) {
                $text = $conv;
            }
        }
        return trim($text);
    }
}
