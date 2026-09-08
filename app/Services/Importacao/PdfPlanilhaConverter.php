<?php

namespace App\Services\Importacao;

use App\Lib\OpenRouter;

/**
 * Converte PDF de demonstrativo (DRE/BP/DFC) em CSV para o fluxo de de-para.
 * BP ALTERDATA → sempre matriz Conta × períodos.
 */
class PdfPlanilhaConverter
{
    /**
     * @return array{ok:bool,destino?:string,nome?:string,fonte?:string,layout?:string,error?:string}
     */
    public function paraArquivoCsv(string $pdfPath, string $dirDestino, string $tipoDemo, string $nomeOriginal): array
    {
        if (!is_dir($dirDestino)) {
            mkdir($dirDestino, 0755, true);
        }

        $tipo = strtoupper(trim($tipoDemo));
        $alter = new AlterdataBpPdfParser();

        // 1) Texto nativo (quando o PDF não é CID)
        if ($tipo === "BP" && $alter->reconhece($pdfPath, $nomeOriginal)) {
            $local = $alter->paraCsv($pdfPath, $nomeOriginal);
            if (!empty($local["ok"])) {
                return $this->gravarCsv((string) $local["csv"], $dirDestino, $nomeOriginal, "alterdata", "matriz");
            }
        }

        if (!OpenRouter::disponivel()) {
            return [
                "ok"    => false,
                "error" => "Importação de PDF requer OPENROUTER_API_KEY (Mistral OCR) ou PDF ALTERDATA com texto nativo.",
            ];
        }

        if (!is_readable($pdfPath)) {
            return ["ok" => false, "error" => "PDF ilegível."];
        }

        try {
            $or = new OpenRouter();
            $ocr = $or->extrairDemonstrativoPdf($pdfPath, $tipoDemo, $nomeOriginal);
            if (empty($ocr["ok"])) {
                return ["ok" => false, "error" => (string) ($ocr["error"] ?? "Falha no OCR do PDF.")];
            }

            $textoOcr = (string) ($ocr["text"] ?? "");

            // 2) OCR → parser ALTERDATA (matriz Conta × datas)
            if ($tipo === "BP") {
                $local = $alter->paraCsvDeTexto($textoOcr, $nomeOriginal);
                if (!empty($local["ok"])) {
                    return $this->gravarCsv((string) $local["csv"], $dirDestino, $nomeOriginal, "alterdata", "matriz");
                }
            }

            // 3) Fallback IA — para BP força prompt de matriz
            $csvOut = $or->textoDemonstrativoParaCsv($textoOcr, $tipoDemo);
            if (empty($csvOut["ok"])) {
                return ["ok" => false, "error" => (string) ($csvOut["error"] ?? "Falha ao estruturar PDF.")];
            }

            $layout = $tipo === "BP" ? "matriz" : "";
            $fonte = $tipo === "BP" && $alter->pareceAlterdata($textoOcr, $nomeOriginal) ? "alterdata" : "ocr";

            return $this->gravarCsv((string) $csvOut["csv"], $dirDestino, $nomeOriginal, $fonte, $layout);
        } catch (\Throwable $e) {
            return ["ok" => false, "error" => $e->getMessage()];
        }
    }

    /**
     * @return array{ok:bool,destino?:string,nome?:string,fonte?:string,layout?:string,error?:string}
     */
    private function gravarCsv(
        string $csv,
        string $dirDestino,
        string $nomeOriginal,
        string $fonte,
        string $layout = ""
    ): array {
        $nomeCsv = pathinfo($nomeOriginal, PATHINFO_FILENAME);
        $nomeCsv = preg_replace('/[^\w\-]+/u', "_", $nomeCsv) ?: "pdf_import";
        $nomeSalvo = $nomeCsv . "_" . time() . ".csv";
        $destino = rtrim($dirDestino, "/\\") . DIRECTORY_SEPARATOR . $nomeSalvo;

        $csv = $this->sanitizarCsvImportacao($csv);

        if (@file_put_contents($destino, $csv) === false) {
            return ["ok" => false, "error" => "Não foi possível gravar CSV convertido."];
        }

        return [
            "ok"      => true,
            "destino" => $destino,
            "nome"    => $nomeSalvo,
            "fonte"   => $fonte,
            "layout"  => $layout,
        ];
    }

    private function sanitizarCsvImportacao(string $csv): string
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', "", $csv) ?? $csv;
        $linhas = preg_split("/\R/u", $csv) ?: [];
        $out = [];
        foreach ($linhas as $linha) {
            if ($linha === "") {
                $out[] = $linha;
                continue;
            }
            $cols = str_getcsv($linha);
            foreach ($cols as $i => $col) {
                $trim = ltrim((string) $col);
                if ($trim !== "" && str_starts_with($trim, "=")) {
                    $cols[$i] = "'" . $col;
                }
            }
            $out[] = implode(",", array_map(static function (string $v): string {
                if (str_contains($v, ",") || str_contains($v, '"') || str_contains($v, "\n")) {
                    return '"' . str_replace('"', '""', $v) . '"';
                }
                return $v;
            }, $cols));
        }

        return implode("\n", $out);
    }
}
