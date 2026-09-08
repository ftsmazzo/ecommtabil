<?php

namespace App\Services\Importacao;

use App\Services\Caixa\PdfTextExtractor;

/**
 * Parser local de BP exportado pelo ALTERDATA (PDF com texto nativo).
 * Gera CSV matriz: Conta × Exercício Atual / Anterior.
 */
class AlterdataBpPdfParser
{
    /**
     * @return array{ok:bool,csv?:string,periodo_atual?:string,periodo_anterior?:string,totais?:array<string,float>,error?:string}
     */
    public function paraCsv(string $pdfPath): array
    {
        $texto = (new PdfTextExtractor())->extract($pdfPath, basename($pdfPath));
        if ($texto === "" || !$this->reconheceTexto($texto)) {
            return ["ok" => false, "error" => "PDF sem texto ALTERDATA legível."];
        }

        return $this->paraCsvDeTexto($texto);
    }

    /**
     * @return array{ok:bool,csv?:string,periodo_atual?:string,periodo_anterior?:string,totais?:array<string,float>,error?:string}
     */
    public function paraCsvDeTexto(string $texto): array
    {
        if (!$this->reconheceTexto($texto)) {
            return ["ok" => false, "error" => "Formato ALTERDATA não reconhecido."];
        }

        return $this->montarCsv($texto);
    }

    public function reconhece(string $pdfPath): bool
    {
        $texto = (new PdfTextExtractor())->extract($pdfPath, basename($pdfPath));
        if ($this->reconheceTexto($texto)) {
            return true;
        }

        return false;
    }

    public function reconheceTexto(string $texto): bool
    {
        if ($texto === "") {
            return false;
        }

        return (bool) preg_match('/Balan[cç]o\s+Patrimonial\s+Encerrado/iu', $texto)
            && (bool) preg_match('/Exerc[ií]cio\s+Atual/iu', $texto)
            && (
                (bool) preg_match('/\d+\.\d+\.\d+\.\d+/', $texto)
                || (bool) preg_match('/,\d{2}[DC]\s+[\d.*]+,\d{2}[DC]/iu', $texto)
                || (bool) preg_match('/\d+(?:\.\d+){3,}/', $texto)
            );
    }

    /**
     * @return array{ok:bool,csv?:string,periodo_atual?:string,periodo_anterior?:string,totais?:array<string,float>,error?:string}
     */
    private function montarCsv(string $texto): array
    {
        $periodoAtual = null;
        if (preg_match('/Encerrado\s+em\s+(\d{2}\/\d{2}\/\d{4})/iu', $texto, $m)) {
            $periodoAtual = $m[1];
        }

        $periodoAnterior = null;
        if ($periodoAtual && preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $periodoAtual, $m)) {
            $periodoAnterior = $m[1] . "/" . $m[2] . "/" . ((int) $m[3] - 1);
        }

        $linhas = preg_split('/\R/u', $texto) ?: [];
        $contas = [];
        $totais = [];

        foreach ($linhas as $linha) {
            $linha = trim($linha);
            if ($linha === "" || str_starts_with($linha, "Folha:") || str_starts_with($linha, "--")) {
                continue;
            }
            if (preg_match('/^(Descrição|Balanço Patrimonial)/iu', $linha)) {
                continue;
            }

            $parsed = $this->parseLinha($linha);
            if (!$parsed) {
                continue;
            }

            if (!empty($parsed["total"])) {
                $totais[$this->normalizar($parsed["descricao"])] = $parsed["atual"]["valor"];
                continue;
            }

            if ($parsed["classificacao"] === "") {
                continue;
            }

            $nome = $this->limparDescricao($parsed["descricao"]);
            if ($nome === "") {
                continue;
            }

            $contas[] = [
                "nome"          => $nome,
                "classificacao" => $parsed["classificacao"],
                "atual"         => $parsed["atual"],
                "anterior"      => $parsed["anterior"],
            ];
        }

        if ($contas === []) {
            return ["ok" => false, "error" => "Nenhuma conta analítica encontrada no PDF ALTERDATA."];
        }

        $colAtual = $periodoAtual ?: "Exercicio Atual";
        $colAnterior = $periodoAnterior ?: "Exercicio Anterior";

        $csv = $this->escapeCsv("Conta") . "," . $this->escapeCsv($colAtual) . "," . $this->escapeCsv($colAnterior) . "\n";
        foreach ($contas as $c) {
            $atual = $this->valorContabil(
                $c["atual"]["valor"],
                $c["atual"]["lado"],
                $c["classificacao"],
                $c["nome"]
            );
            $anterior = $this->valorContabil(
                $c["anterior"]["valor"],
                $c["anterior"]["lado"],
                $c["classificacao"],
                $c["nome"]
            );
            $csv .= $this->escapeCsv($c["nome"]) . ","
                . $this->formatarValorCsv($atual) . ","
                . $this->formatarValorCsv($anterior) . "\n";
        }

        return [
            "ok"               => true,
            "csv"              => $csv,
            "periodo_atual"    => $periodoAtual,
            "periodo_anterior" => $periodoAnterior,
            "totais"           => $totais,
        ];
    }

    /**
     * @return ?array{
     *   descricao:string,
     *   classificacao:string,
     *   atual:array{valor:float,lado:string},
     *   anterior:array{valor:float,lado:string},
     *   total:bool
     * }
     */
    private function parseLinha(string $linha): ?array
    {
        if (!preg_match(
            '/^(?<atual>[\d.*]+,\d{2}[DC])\s+(?<anterior>[\d.*]+,\d{2}[DC])\s+(?<resto>.+)$/iu',
            $linha,
            $m
        )) {
            return null;
        }

        $resto = trim($m["resto"]);
        $classificacao = "";
        if (preg_match('/(\d+(?:\.\d+)+)\s*$/', $resto, $cm)) {
            $classificacao = $cm[1];
            $resto = trim(substr($resto, 0, -strlen($cm[0])));
        }

        $descricao = trim($resto);
        $total = str_starts_with($descricao, "=") || str_contains($this->normalizar($descricao), "total");

        return [
            "descricao"     => ltrim($descricao, "= "),
            "classificacao" => $classificacao,
            "atual"         => $this->parseValorAlterdata($m["atual"]),
            "anterior"      => $this->parseValorAlterdata($m["anterior"]),
            "total"         => $total,
        ];
    }

    /**
     * @return array{valor:float,lado:string}
     */
    private function parseValorAlterdata(string $raw): array
    {
        $raw = trim($raw);
        $lado = strtoupper(substr($raw, -1));
        $num  = preg_replace('/[^\d,]/', "", substr($raw, 0, -1)) ?? "";
        $num  = str_replace(".", "", $num);
        $num  = str_replace(",", ".", $num);
        $valor = (float) $num;

        return ["valor" => $valor, "lado" => in_array($lado, ["D", "C"], true) ? $lado : ""];
    }

    private function limparDescricao(string $desc): string
    {
        $desc = preg_replace('/\s*\(\d+\)\s*$/', "", $desc) ?? $desc;
        $desc = preg_replace('/\s+/', " ", $desc) ?? $desc;

        return trim($desc);
    }

    private function valorContabil(float $valor, string $lado, string $classificacao, string $descricao): float
    {
        if (abs($valor) < 0.00001) {
            return 0.0;
        }

        $desc = mb_strtolower($descricao, "UTF-8");
        if (str_contains($desc, "prejuízo") || str_contains($desc, "prejuizo") || str_contains($descricao, "(-)")) {
            return -abs($valor);
        }

        $grupo = substr($classificacao, 0, 3);
        if ($grupo === "1.1" || str_starts_with($classificacao, "1.")) {
            return $lado === "C" ? -abs($valor) : abs($valor);
        }

        if (str_starts_with($classificacao, "2.3")) {
            return $lado === "C" ? abs($valor) : -abs($valor);
        }

        if (str_starts_with($classificacao, "2.")) {
            return $lado === "C" ? -abs($valor) : abs($valor);
        }

        return $lado === "C" ? -abs($valor) : abs($valor);
    }

    private function formatarValorCsv(float $v): string
    {
        if (abs($v) < 0.00001) {
            return "0";
        }

        return number_format($v, 2, ".", "");
    }

    private function escapeCsv(string $v): string
    {
        if (str_contains($v, ",") || str_contains($v, '"') || str_contains($v, "\n")) {
            return '"' . str_replace('"', '""', $v) . '"';
        }

        return $v;
    }

    private function normalizar(string $t): string
    {
        $t = mb_strtolower(trim($t), "UTF-8");
        $ascii = @iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $t);

        return (string) preg_replace("/[^a-z0-9]+/", "", is_string($ascii) ? $ascii : $t);
    }
}
