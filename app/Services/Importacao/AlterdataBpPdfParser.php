<?php

namespace App\Services\Importacao;

use App\Services\Caixa\PdfTextExtractor;

/**
 * Parser de BP exportado pelo ALTERDATA.
 * Saída: CSV matriz Conta × Exercício Atual / Anterior (datas dd/mm/aaaa).
 */
class AlterdataBpPdfParser
{
    /**
     * @return array{ok:bool,csv?:string,periodo_atual?:string,periodo_anterior?:string,totais?:array<string,float>,error?:string}
     */
    public function paraCsv(string $pdfPath, string $nomeOriginal = ""): array
    {
        $texto = (new PdfTextExtractor())->extract($pdfPath, $nomeOriginal !== "" ? $nomeOriginal : basename($pdfPath));
        if ($texto === "") {
            return ["ok" => false, "error" => "PDF sem texto legível."];
        }

        return $this->paraCsvDeTexto($texto, $nomeOriginal);
    }

    /**
     * @return array{ok:bool,csv?:string,periodo_atual?:string,periodo_anterior?:string,totais?:array<string,float>,error?:string}
     */
    public function paraCsvDeTexto(string $texto, string $nomeOriginal = ""): array
    {
        if (!$this->pareceAlterdata($texto, $nomeOriginal)) {
            return ["ok" => false, "error" => "Formato ALTERDATA não reconhecido."];
        }

        return $this->montarCsv($texto);
    }

    public function reconhece(string $pdfPath, string $nomeOriginal = ""): bool
    {
        $nome = $nomeOriginal !== "" ? $nomeOriginal : basename($pdfPath);
        if ($this->nomeSugereAlterdata($nome)) {
            return true;
        }
        $texto = (new PdfTextExtractor())->extract($pdfPath, $nome);

        return $this->pareceAlterdata($texto, $nome);
    }

    public function reconheceTexto(string $texto, string $nomeOriginal = ""): bool
    {
        return $this->pareceAlterdata($texto, $nomeOriginal);
    }

    /**
     * Reconhecimento frouxo: OCR costuma bagunçar acentos/espaços.
     */
    public function pareceAlterdata(string $texto, string $nomeOriginal = ""): bool
    {
        if ($this->nomeSugereAlterdata($nomeOriginal)) {
            return true;
        }
        if ($texto === "") {
            return false;
        }

        $temBalanco = (bool) preg_match('/Balan[cç]?o\s+Patrimonial/iu', $texto)
            || (bool) preg_match('/Encerrado\s+em\s+\d{2}\/\d{2}\/\d{4}/iu', $texto);
        $temExercicio = (bool) preg_match('/Exerc[ií]cio\s+Atual/iu', $texto)
            || (bool) preg_match('/Exerc[ií]cio\s+Anterior/iu', $texto);
        $temValorDc = (bool) preg_match('/\d{1,3}(?:\.\d{3})*,\d{2}\s*[DC]/iu', $texto);
        $temCodigo = (bool) preg_match('/\d+(?:\.\d+){3,}/', $texto);

        return $temBalanco && ($temExercicio || $temValorDc) && ($temValorDc || $temCodigo);
    }

    public function nomeSugereAlterdata(string $nome): bool
    {
        $n = $this->normalizar($nome);

        return str_contains($n, "alterdata")
            || str_contains($n, "balanco")
            || str_contains($n, "balancopatrimonial");
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
        $jaTem = [];

        foreach ($linhas as $linha) {
            $linha = trim(preg_replace('/\s+/u', ' ', $linha) ?? $linha);
            if ($linha === "" || str_starts_with($linha, "Folha:") || str_starts_with($linha, "--")) {
                continue;
            }
            if (preg_match('/^(Descri[cç][aã]o|Balan[cç]o Patrimonial|PASSIVO E|ATIVO\b|Classifica)/iu', $linha)) {
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
            if ($nome === "" || isset($jaTem[$this->normalizar($nome)])) {
                continue;
            }

            $contas[] = [
                "nome"          => $nome,
                "classificacao" => $parsed["classificacao"],
                "atual"         => $parsed["atual"],
                "anterior"      => $parsed["anterior"],
            ];
            $jaTem[$this->normalizar($nome)] = true;
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
        // Senzi/ALTERDATA: "18.231,36D0,00D Banco..." (sem espaço entre atual e anterior)
        // Padrão com espaço: "4.245,68D  0,00D  Caixa (35)  1.1.01.001.00001"
        if (preg_match(
            '/^(?<atual>[\d.*]+,\d{2}\s*[DC])\s*(?<anterior>[\d.*]+,\d{2}\s*[DC])\s+(?<resto>.+)$/iu',
            $linha,
            $m
        )) {
            return $this->montarParsed($m["atual"], $m["anterior"], $m["resto"]);
        }

        // OCR às vezes inverte: Nome ... código ... 4.245,68 D  0,00 D
        if (preg_match(
            '/^(?<resto>.+?)\s+(?<atual>[\d.*]+,\d{2}\s*[DC])\s*(?<anterior>[\d.*]+,\d{2}\s*[DC])\s*$/iu',
            $linha,
            $m
        )) {
            return $this->montarParsed($m["atual"], $m["anterior"], $m["resto"]);
        }

        // Só um valor D/C + código no fim
        if (preg_match(
            '/^(?<atual>[\d.*]+,\d{2}\s*[DC])\s+(?<resto>.+)$/iu',
            $linha,
            $m
        ) && preg_match('/\d+(?:\.\d+){3,}\s*$/', $m["resto"])) {
            return $this->montarParsed($m["atual"], "0,00D", $m["resto"]);
        }

        return null;
    }

    /**
     * @return array{
     *   descricao:string,
     *   classificacao:string,
     *   atual:array{valor:float,lado:string},
     *   anterior:array{valor:float,lado:string},
     *   total:bool
     * }
     */
    private function montarParsed(string $atualRaw, string $anteriorRaw, string $resto): array
    {
        $resto = trim($resto);
        $classificacao = "";
        if (preg_match('/(\d+(?:\.\d+){2,})\s*$/', $resto, $cm)) {
            $classificacao = $cm[1];
            $resto = trim(substr($resto, 0, -strlen($cm[0])));
        }

        $descricao = trim($resto);
        $norm = $this->normalizar($descricao);
        $total = str_starts_with($descricao, "=")
            || str_contains($norm, "total")
            || str_contains($norm, "passivoadescoberto")
            || str_starts_with($descricao, "*");

        return [
            "descricao"     => ltrim($descricao, "=* "),
            "classificacao" => $classificacao,
            "atual"         => $this->parseValorAlterdata($atualRaw),
            "anterior"      => $this->parseValorAlterdata($anteriorRaw),
            "total"         => $total,
        ];
    }

    /**
     * @return array{valor:float,lado:string}
     */
    private function parseValorAlterdata(string $raw): array
    {
        $raw = trim($raw);
        $lado = strtoupper(substr(preg_replace('/\s+/', '', $raw) ?? $raw, -1));
        $num  = preg_replace('/[^\d,]/', "", $raw) ?? "";
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

        if (str_starts_with($classificacao, "1.")) {
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
