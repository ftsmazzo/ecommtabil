<?php

namespace App\Services\Caixa;

/**
 * Extrai comprovantes individuais de um PDF multi-página (ex. COMPROVANTES 07-26.pdf).
 */
class ComprovantePdfParser
{
    public function __construct(
        private PdfTextExtractor $extractor = new PdfTextExtractor()
    ) {
    }

    /**
     * @return array<int,array{
     *   valor:?float,data:?string,contraparte:?string,ident_extrato:?string,texto:string
     * }>
     */
    public function parseFile(string $path): array
    {
        $text = $this->extractor->extract($path);
        if ($text === "") {
            throw new \RuntimeException("Não foi possível ler texto do PDF de comprovantes.");
        }
        return $this->parse($text);
    }

    /**
     * @return array<int,array{valor:?float,data:?string,contraparte:?string,ident_extrato:?string,texto:string}>
     */
    public function parse(string $text): array
    {
        $partes = preg_split(
            '/(?=(?:Banco\s+Ita[úu]|Comprovante\s+de\s+Transfer))/iu',
            $text
        ) ?: [];

        $out = [];
        foreach ($partes as $parte) {
            $parte = trim($parte);
            if ($parte === "" || mb_strlen($parte) < 40) {
                continue;
            }
            $item = $this->parseBloco($parte);
            if ($item !== null) {
                $out[] = $item;
            }
        }

        if ($out === [] && preg_match('/Valor:\s*R\$/iu', $text)) {
            $item = $this->parseBloco($text);
            if ($item !== null) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * @return array{valor:?float,data:?string,contraparte:?string,ident_extrato:?string,texto:string}|null
     */
    private function parseBloco(string $bloco): ?array
    {
        $valor = null;
        if (preg_match('/Valor:\s*R\$\s*([0-9]{1,3}(?:\.[0-9]{3})*,[0-9]{2})/iu', $bloco, $m)) {
            $valor = $this->valorBr($m[1]);
        }

        $data = null;
        if (preg_match('/(?:efetuada|realizada|pag[ao])\s+em\s+(\d{2}\/\d{2}\/\d{4})/iu', $bloco, $m)) {
            $data = $this->dataBr($m[1]);
        } elseif (preg_match('/\b(\d{2}\/\d{2}\/\d{4})\b/', $bloco, $m)) {
            $data = $this->dataBr($m[1]);
        }

        $ident = null;
        if (preg_match('/Identifica[çc][ãa]o\s+no\s+extrato:\s*(.+?)(?:\n|Dados)/iu', $bloco, $m)) {
            $ident = trim(preg_replace('/\s+/u', " ", $m[1]) ?? $m[1]);
        }

        $contraparte = null;
        if (preg_match('/Dados da conta creditada:.*?Nome:\s*(.+?)(?:\n|Ag[êe]ncia)/ius', $bloco, $m)) {
            $contraparte = trim(preg_replace('/\s+/u', " ", $m[1]) ?? $m[1]);
        } elseif (preg_match('/Nome:\s*(.+?)(?:\n|Ag[êe]ncia)/iu', $bloco, $m)) {
            $contraparte = trim(preg_replace('/\s+/u', " ", $m[1]) ?? $m[1]);
        }

        if ($valor === null && $data === null && $ident === null) {
            return null;
        }

        return [
            "valor"         => $valor,
            "data"          => $data,
            "contraparte"   => $contraparte,
            "ident_extrato" => $ident,
            "texto"         => mb_substr($bloco, 0, 8000),
        ];
    }

    private function dataBr(string $d): string
    {
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $d, $m)) {
            return sprintf("%s-%s-%s", $m[3], $m[2], $m[1]);
        }
        return $d;
    }

    private function valorBr(string $v): float
    {
        $v = str_replace(".", "", $v);
        $v = str_replace(",", ".", $v);
        return round((float) $v, 2);
    }
}
