<?php

namespace App\Services\Caixa;

/**
 * Parser de extrato bancário PDF (formato Itaú empresas — ex. Extrato 07-26.pdf).
 */
class ExtratoPdfParser
{
    public function __construct(
        private PdfTextExtractor $extractor = new PdfTextExtractor()
    ) {
    }

    /**
     * @return array{
     *   banco_nome:?string,banco_id:?string,agencia:?string,conta:?string,
     *   periodo_inicio:?string,periodo_fim:?string,cnpj:?string,empresa:?string,
     *   movimentos:array<int,array{fitid:string,data_posted:string,tipo:string,valor:float,memo:string}>
     * }
     */
    public function parseFile(string $path): array
    {
        $text = $this->extractor->extract($path);
        if ($text === "") {
            throw new \RuntimeException("Não foi possível ler texto do PDF do extrato.");
        }
        return $this->parse($text);
    }

    /**
     * @return array{
     *   banco_nome:?string,banco_id:?string,agencia:?string,conta:?string,
     *   periodo_inicio:?string,periodo_fim:?string,cnpj:?string,empresa:?string,
     *   movimentos:array<int,array{fitid:string,data_posted:string,tipo:string,valor:float,memo:string}>
     * }
     */
    public function parse(string $text): array
    {
        $meta = $this->metaCabecalho($text);
        $blocos = $this->blocosTransacao($text);
        $movimentos = [];
        $seq = 0;

        foreach ($blocos as $bloco) {
            $parsed = $this->parseBloco($bloco);
            if ($parsed === null) {
                continue;
            }
            $seq++;
            $parsed["fitid"] = sprintf(
                "%s-%s-%d",
                str_replace("/", "", $parsed["data_posted"]),
                preg_replace('/[^a-z0-9]+/i', "", mb_substr($parsed["memo"], 0, 24)) ?: "mov",
                $seq
            );
            $movimentos[] = $parsed;
        }

        if ($movimentos === []) {
            throw new \RuntimeException("Nenhuma movimentação encontrada no PDF do extrato.");
        }

        $datas = array_column($movimentos, "data_posted");
        sort($datas);

        return array_merge($meta, [
            "banco_nome"     => $meta["banco_nome"] ?? "Itaú",
            "periodo_inicio" => $meta["periodo_inicio"] ?? $datas[0],
            "periodo_fim"    => $meta["periodo_fim"] ?? $datas[count($datas) - 1],
            "movimentos"     => $movimentos,
        ]);
    }

    /**
     * @return array{banco_nome:?string,agencia:?string,conta:?string,periodo_inicio:?string,periodo_fim:?string,cnpj:?string,empresa:?string,banco_id:?string}
     */
    private function metaCabecalho(string $text): array
    {
        $out = [
            "banco_nome" => "Itaú",
            "banco_id"   => null,
            "agencia"    => null,
            "conta"      => null,
            "cnpj"       => null,
            "empresa"    => null,
            "periodo_inicio" => null,
            "periodo_fim"    => null,
        ];

        if (preg_match('/Ag[êe]ncia\s+(\d+)\s+Conta\s+([\d\-]+)/iu', $text, $m)) {
            $out["agencia"] = $m[1];
            $out["conta"]   = $m[2];
        }
        if (preg_match('/CNPJ\s+([\d.\/\-]+)/iu', $text, $m)) {
            $out["cnpj"] = $m[1];
        }
        if (preg_match('/CNPJ\s+[\d.\/\-]+\s*([A-Z0-9][A-Z0-9\s\.]{4,60})/iu', $text, $m)) {
            $out["empresa"] = trim($m[1]);
        }
        if (preg_match(
            '/Lan[çc]amentos do per[íi]odo:\s*(\d{2}\/\d{2}\/\d{4})\s+at[ée]\s+(\d{2}\/\d{2}\/\d{4})/iu',
            $text,
            $m
        )) {
            $out["periodo_inicio"] = $this->dataBr($m[1]);
            $out["periodo_fim"]    = $this->dataBr($m[2]);
        }

        return $out;
    }

    /**
     * @return array<int,string>
     */
    private function blocosTransacao(string $text): array
    {
        $lines = preg_split('/\n/u', $text) ?: [];
        $blocos = [];
        $atual = "";

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === "") {
                continue;
            }
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}\b/u', $line)) {
                if ($atual !== "") {
                    $blocos[] = $atual;
                }
                $atual = $line;
            } elseif ($atual !== "") {
                $atual .= " " . $line;
            }
        }
        if ($atual !== "") {
            $blocos[] = $atual;
        }

        return $blocos;
    }

    /**
     * @return array{data_posted:string,tipo:string,valor:float,memo:string}|null
     */
    private function parseBloco(string $bloco): ?array
    {
        if (!preg_match('/^(\d{2}\/\d{2}\/\d{4})\s+(.+)$/u', $bloco, $m)) {
            return null;
        }

        $data = $this->dataBr($m[1]);
        $resto = trim($m[2]);

        if ($this->pareceSaldo($resto)) {
            return null;
        }

        $valor = null;
        if (preg_match('/(-?\d{1,3}(?:\.\d{3})*,\d{2})\s*$/u', $resto, $vm)) {
            $valor = $this->valorBr($vm[1]);
            $resto = trim(substr($resto, 0, -strlen($vm[1])));
        }

        if ($valor === null) {
            return null;
        }

        $memo = preg_replace('/\s+/u', " ", $resto) ?? $resto;
        $memo = mb_substr(trim($memo), 0, 500);

        $tipo = $valor >= 0 ? "credit" : "debit";

        return [
            "data_posted" => $data,
            "tipo"        => $tipo,
            "valor"       => round($valor, 2),
            "memo"        => $memo,
        ];
    }

    private function pareceSaldo(string $resto): bool
    {
        $u = mb_strtoupper($resto, "UTF-8");
        foreach ([
            "SALDO ANTERIOR",
            "SALDO TOTAL DISPON",
            "SALDO MOVIMENTA",
            "SALDO APLIC",
            "SALDO TOTAL",
        ] as $skip) {
            if (str_contains($u, $skip)) {
                return true;
            }
        }
        return false;
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
        $v = trim($v);
        $neg = str_starts_with($v, "-");
        $v = ltrim($v, "-");
        $v = str_replace(".", "", $v);
        $v = str_replace(",", ".", $v);
        $f = (float) $v;
        return $neg ? -$f : $f;
    }
}
