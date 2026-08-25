<?php

namespace App\Services\Importacao;

/**
 * Classifica a ORIGEM pelo formato das colunas, não pelo nome do arquivo
 * nem por um marketplace fixo. Famílias conhecidas são atalhos; o restante
 * cai em "desconhecida" e segue o de-para pelo modelo.
 */
class OrigemClassificador
{
    public const FAMILIA_MATRIZ = "matriz_demonstrativo";
    public const FAMILIA_MARKETPLACE = "ledger_marketplace";
    public const FAMILIA_ERP = "ledger_operacional";
    public const FAMILIA_OFX = "extrato_ofx";
    public const FAMILIA_DESCONHECIDA = "desconhecida";

    /**
     * @param array<int,string> $headers
     * @return array{familia:string,layout:string,confianca:int,sinais:array<int,string>}
     */
    public function classificar(array $headers, string $extensao = ""): array
    {
        $ext = strtolower(ltrim($extensao, "."));
        if ($ext === "ofx") {
            return [
                "familia"   => self::FAMILIA_OFX,
                "layout"    => PlanilhaImportacaoService::LAYOUT_LEDGER,
                "confianca" => 95,
                "sinais"    => ["extensao ofx"],
            ];
        }

        $norm = array_map([$this, "n"], $headers);
        $blob = " " . implode(" ", $norm) . " ";
        $sinais = [];

        $meses = 0;
        foreach (["jan", "fev", "mar", "abr", "mai", "jun", "jul", "ago", "set", "out", "nov", "dez"] as $m) {
            foreach ($norm as $h) {
                if ($h === $m || str_starts_with($h, $m)) {
                    $meses++;
                    break;
                }
            }
        }
        $temLinha = $this->tem($blob, ["linhar", "linha", "conta", "descricaodaconta"]);
        if ($meses >= 6 || ($meses >= 3 && $temLinha)) {
            $sinais[] = "colunas de mês (matriz)";
            return [
                "familia"   => self::FAMILIA_MATRIZ,
                "layout"    => PlanilhaImportacaoService::LAYOUT_MATRIZ,
                "confianca" => min(95, 50 + $meses * 5),
                "sinais"    => $sinais,
            ];
        }

        $temReceitaMp = $this->tem($blob, [
            "receitaporproduto", "ingresosporproducto", "precoacordado",
            "subtotaldoproduto", "valortotaldoproduto",
        ]);
        $temTarifa = $this->tem($blob, [
            "tarifadevenda", "tarifadeventa", "taxadecomissao", "taxadeservico",
            "cargoporservicio", "comissaomarketplace",
        ]);
        $temPedido = $this->tem($blob, [
            "iddopedido", "npedido", "numerodavenda", "nvenda", "orderid",
        ]);
        if (($temReceitaMp || $temPedido) && ($temTarifa || $temReceitaMp)) {
            $sinais[] = "ledger de marketplace (pedido + valores de canal)";
            return [
                "familia"   => self::FAMILIA_MARKETPLACE,
                "layout"    => PlanilhaImportacaoService::LAYOUT_COLUNAR,
                "confianca" => 80,
                "sinais"    => $sinais,
            ];
        }

        $temVendaOp = $this->tem($blob, ["vrvenda", "valorvenda", "venda", "precovenda"]);
        $temCustoOp = $this->tem($blob, ["custo", "cmv", "precodecompra"]);
        if ($temVendaOp && $temCustoOp && count($headers) <= 25) {
            $sinais[] = "ledger operacional curto (ERP/planilha interna)";
            return [
                "familia"   => self::FAMILIA_ERP,
                "layout"    => PlanilhaImportacaoService::LAYOUT_COLUNAR,
                "confianca" => 70,
                "sinais"    => $sinais,
            ];
        }

        return [
            "familia"   => self::FAMILIA_DESCONHECIDA,
            "layout"    => "",
            "confianca" => 0,
            "sinais"    => ["formato não catalogado — usa o modelo interno"],
        ];
    }

    /**
     * Impressão digital estável: ordem e texto normalizado dos cabeçalhos.
     * Serve para reusar o de-para em qualquer projeto/ambiente com o mesmo layout.
     *
     * @param array<int,string> $headers
     */
    public function fingerprint(array $headers): string
    {
        $parts = [];
        foreach ($headers as $h) {
            $parts[] = $this->n((string) $h);
        }
        return hash("sha256", implode("\n", $parts));
    }

    public function n(string $t): string
    {
        $t = mb_strtolower(trim($t), "UTF-8");
        $ascii = @iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $t);
        $t = is_string($ascii) ? $ascii : $t;
        $t = (string) preg_replace("/[^a-z0-9]+/", "", $t);
        $t = (string) preg_replace("/(en)?(brl|usd|ars|mxn|cop|eur|rs)$/", "", $t);
        return $t;
    }

    /**
     * @param array<int,string> $tokens
     */
    private function tem(string $blob, array $tokens): bool
    {
        foreach ($tokens as $tok) {
            if ($tok !== "" && str_contains($blob, $tok)) {
                return true;
            }
        }
        return false;
    }
}
