<?php

namespace App\Services\Importacao;

use App\Lib\OpenRouter;

/**
 * Converte PDF de demonstrativo (DRE/BP/DFC) em CSV para o fluxo de de-para.
 */
class PdfPlanilhaConverter
{
    /**
     * @return array{ok:bool,destino?:string,nome?:string,error?:string}
     */
    public function paraArquivoCsv(string $pdfPath, string $dirDestino, string $tipoDemo, string $nomeOriginal): array
    {
        if (!is_dir($dirDestino)) {
            mkdir($dirDestino, 0755, true);
        }

        if (strtoupper(trim($tipoDemo)) === "BP") {
            $alter = new AlterdataBpPdfParser();
            if ($alter->reconhece($pdfPath)) {
                $local = $alter->paraCsv($pdfPath);
                if (!empty($local["ok"])) {
                    return $this->gravarCsv($local["csv"], $dirDestino, $nomeOriginal, "alterdata");
                }
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
            if (strtoupper(trim($tipoDemo)) === "BP") {
                $alter = new AlterdataBpPdfParser();
                if ($alter->reconheceTexto($textoOcr)) {
                    $local = $alter->paraCsvDeTexto($textoOcr);
                    if (!empty($local["ok"])) {
                        return $this->gravarCsv((string) $local["csv"], $dirDestino, $nomeOriginal, "alterdata");
                    }
                }
            }

            $csvOut = $or->textoDemonstrativoParaCsv($textoOcr, $tipoDemo);
            if (empty($csvOut["ok"])) {
                return ["ok" => false, "error" => (string) ($csvOut["error"] ?? "Falha ao estruturar PDF.")];
            }

            return $this->gravarCsv((string) $csvOut["csv"], $dirDestino, $nomeOriginal, "ocr");
        } catch (\Throwable $e) {
            return ["ok" => false, "error" => $e->getMessage()];
        }
    }

    /**
     * @return array{ok:bool,destino?:string,nome?:string,fonte?:string,error?:string}
     */
    private function gravarCsv(string $csv, string $dirDestino, string $nomeOriginal, string $fonte): array
    {
        $nomeCsv = pathinfo($nomeOriginal, PATHINFO_FILENAME);
        $nomeCsv = preg_replace('/[^\w\-]+/u', "_", $nomeCsv) ?: "pdf_import";
        $nomeSalvo = $nomeCsv . "_" . time() . ".csv";
        $destino = rtrim($dirDestino, "/\\") . DIRECTORY_SEPARATOR . $nomeSalvo;

        if (@file_put_contents($destino, $csv) === false) {
            return ["ok" => false, "error" => "Não foi possível gravar CSV convertido."];
        }

        return ["ok" => true, "destino" => $destino, "nome" => $nomeSalvo, "fonte" => $fonte];
    }
}
