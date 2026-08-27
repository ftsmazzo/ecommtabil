<?php

namespace App\Services\Caixa;

/**
 * Lê OFX 1.x (SGML) como o modelo em modelos/extrato_modelo_*.ofx.
 * Não depende de lib externa.
 */
class OfxParser
{
    /**
     * @return array{
     *   banco_nome:?string,
     *   banco_id:?string,
     *   agencia:?string,
     *   conta:?string,
     *   periodo_inicio:?string,
     *   periodo_fim:?string,
     *   movimentos:array<int,array{
     *     fitid:string,
     *     data_posted:string,
     *     tipo:string,
     *     valor:float,
     *     memo:string
     *   }>
     * }
     */
    public function parseFile(string $path): array
    {
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === "") {
            throw new \RuntimeException("Não foi possível ler o arquivo OFX.");
        }
        return $this->parse($raw);
    }

    /**
     * @return array{
     *   banco_nome:?string,
     *   banco_id:?string,
     *   agencia:?string,
     *   conta:?string,
     *   periodo_inicio:?string,
     *   periodo_fim:?string,
     *   movimentos:array<int,array{fitid:string,data_posted:string,tipo:string,valor:float,memo:string}>
     * }
     */
    public function parse(string $raw): array
    {
        $raw = $this->normalizar($raw);

        $bancoNome = $this->tag($raw, "ORG");
        $bancoId   = $this->tag($raw, "FID") ?: $this->tag($raw, "BANKID");
        $agencia   = $this->tag($raw, "BRANCHID");
        $conta     = $this->tag($raw, "ACCTID");
        $dtStart   = $this->dataOfx($this->tag($raw, "DTSTART"));
        $dtEnd     = $this->dataOfx($this->tag($raw, "DTEND"));

        $movimentos = [];
        if (preg_match_all('/<STMTTRN>(.*?)<\/STMTTRN>/si', $raw, $blocos)) {
            foreach ($blocos[1] as $bloco) {
                $fitid = trim((string) $this->tag($bloco, "FITID"));
                $amt   = $this->tag($bloco, "TRNAMT");
                $dt    = $this->dataOfx($this->tag($bloco, "DTPOSTED"));
                if ($fitid === "" || $amt === null || $dt === null) {
                    continue;
                }
                $valor = (float) str_replace(",", ".", $amt);
                $trn   = strtoupper(trim((string) $this->tag($bloco, "TRNTYPE")));
                $tipo  = $this->mapTipo($trn, $valor);
                $memo  = trim((string) ($this->tag($bloco, "MEMO") ?: $this->tag($bloco, "NAME") ?: ""));
                if (mb_strlen($memo) > 500) {
                    $memo = mb_substr($memo, 0, 500);
                }
                $movimentos[] = [
                    "fitid"        => $fitid,
                    "data_posted"  => $dt,
                    "tipo"         => $tipo,
                    "valor"        => round($valor, 2),
                    "memo"         => $memo,
                ];
            }
        }

        if ($movimentos === []) {
            throw new \RuntimeException("Nenhuma transação encontrada no OFX.");
        }

        if ($dtStart === null || $dtEnd === null) {
            $datas = array_column($movimentos, "data_posted");
            sort($datas);
            $dtStart = $dtStart ?? $datas[0];
            $dtEnd   = $dtEnd ?? $datas[count($datas) - 1];
        }

        return [
            "banco_nome"      => $bancoNome,
            "banco_id"        => $bancoId,
            "agencia"         => $agencia,
            "conta"           => $conta,
            "periodo_inicio"  => $dtStart,
            "periodo_fim"     => $dtEnd,
            "movimentos"      => $movimentos,
        ];
    }

    private function normalizar(string $raw): string
    {
        if (!mb_check_encoding($raw, "UTF-8")) {
            $conv = @mb_convert_encoding($raw, "UTF-8", "Windows-1252,ISO-8859-1,UTF-8");
            if (is_string($conv) && $conv !== "") {
                $raw = $conv;
            }
        }
        // Tags OFX 1.x às vezes vêm sem fechamento; garante STMTTRN fechado se já estiver.
        return $raw;
    }

    private function tag(string $xml, string $name): ?string
    {
        if (preg_match('/<' . preg_quote($name, '/') . '>([^<\r\n]*)/i', $xml, $m)) {
            $v = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, "UTF-8"));
            return $v === "" ? null : $v;
        }
        return null;
    }

    private function dataOfx(?string $v): ?string
    {
        if ($v === null || $v === "") {
            return null;
        }
        $v = preg_replace('/\[.*$/', "", $v) ?? $v;
        $v = preg_replace('/[^0-9]/', "", $v) ?? $v;
        if (strlen($v) < 8) {
            return null;
        }
        $y = substr($v, 0, 4);
        $m = substr($v, 4, 2);
        $d = substr($v, 6, 2);
        if (!checkdate((int) $m, (int) $d, (int) $y)) {
            return null;
        }
        return "{$y}-{$m}-{$d}";
    }

    private function mapTipo(string $trn, float $valor): string
    {
        if (in_array($trn, ["CREDIT", "DEP", "DIRECTDEP", "DIV", "XFER"], true) && $valor >= 0) {
            return "credit";
        }
        if (in_array($trn, ["DEBIT", "POS", "ATM", "CHECK", "PAYMENT", "CASH", "FEE"], true) || $valor < 0) {
            return "debit";
        }
        if ($valor > 0) {
            return "credit";
        }
        if ($valor < 0) {
            return "debit";
        }
        return "other";
    }
}
