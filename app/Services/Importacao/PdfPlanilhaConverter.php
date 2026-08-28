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
        if (!OpenRouter::disponivel()) {
            return [
                "ok"    => false,
                "error" => "Importação de PDF requer OPENROUTER_API_KEY (Mistral OCR).",
            ];
        }

        if (!is_dir($dirDestino)) {
            mkdir($dirDestino, 0755, true);
        }

        try {
            $or = new OpenRouter();
            $ocr = $or->extrairDemonstrativoPdf($pdfPath, $tipoDemo, $nomeOriginal);
            if (empty($ocr["ok"])) {
                return ["ok" => false, "error" => (string) ($ocr["error"] ?? "Falha no OCR do PDF.")];
            }

            $csvOut = $or->textoDemonstrativoParaCsv((string) ($ocr["text"] ?? ""), $tipoDemo);
            if (empty($csvOut["ok"])) {
                return ["ok" => false, "error" => (string) ($csvOut["error"] ?? "Falha ao estruturar PDF.")];
            }

            $nomeCsv = pathinfo($nomeOriginal, PATHINFO_FILENAME);
            $nomeCsv = preg_replace('/[^\w\-]+/u', "_", $nomeCsv) ?: "pdf_import";
            $nomeSalvo = $nomeCsv . "_" . time() . ".csv";
            $destino = rtrim($dirDestino, "/\\") . DIRECTORY_SEPARATOR . $nomeSalvo;

            if (@file_put_contents($destino, (string) $csvOut["csv"]) === false) {
                return ["ok" => false, "error" => "Não foi possível gravar CSV convertido."];
            }

            return ["ok" => true, "destino" => $destino, "nome" => $nomeSalvo];
        } catch (\Throwable $e) {
            return ["ok" => false, "error" => $e->getMessage()];
        }
    }
}
