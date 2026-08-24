<?php

namespace App\Services\Importacao;

use App\Models\DreConta;

/**
 * Motor de de-para: casa cabeçalho da planilha com campo do sistema.
 * Não usa IA. A IA só preenche buracos em cima disto.
 */
class DeParaMapper
{
    /**
     * @param array<int,string> $headers
     * @param array<int,object> $contas
     * @return array{campos: array<string,int>, periodos_matriz: array<int,int>}
     */
    public function sugerir(array $headers, string $layout, array $contas = []): array
    {
        $campos   = [];
        $periodos = [];
        $norm     = array_map([$this, "normalizar"], $headers);
        $usados   = [];

        foreach ($norm as $i => $h) {
            if (!isset($campos[PlanilhaImportacaoService::DEST_PERIODO]) && $this->parecePeriodo($h)) {
                $campos[PlanilhaImportacaoService::DEST_PERIODO] = (int) $i;
                $usados[$i] = true;
            }
        }

        if ($layout === PlanilhaImportacaoService::LAYOUT_MATRIZ) {
            foreach ($norm as $i => $h) {
                if (isset($usados[$i])) {
                    continue;
                }
                if (!isset($campos[PlanilhaImportacaoService::DEST_CONTA]) && $this->pareceConta($h)) {
                    $campos[PlanilhaImportacaoService::DEST_CONTA] = (int) $i;
                    $usados[$i] = true;
                } elseif ($this->pareceCabecalhoPeriodo($h)) {
                    $periodos[] = (int) $i;
                    $usados[$i] = true;
                }
            }
            if (!isset($campos[PlanilhaImportacaoService::DEST_CONTA]) && $headers !== []) {
                $campos[PlanilhaImportacaoService::DEST_CONTA] = 0;
                $periodos = array_values(array_filter($periodos, fn ($i) => $i !== 0));
            }
            return ["campos" => $campos, "periodos_matriz" => $periodos];
        }

        foreach ($norm as $i => $h) {
            if (isset($usados[$i])) {
                continue;
            }
            if (!isset($campos[PlanilhaImportacaoService::DEST_CONTA]) && $this->pareceConta($h)) {
                $campos[PlanilhaImportacaoService::DEST_CONTA] = (int) $i;
                $usados[$i] = true;
            } elseif (!isset($campos[PlanilhaImportacaoService::DEST_UNIDADE]) && $this->pareceUnidade($h)) {
                $campos[PlanilhaImportacaoService::DEST_UNIDADE] = (int) $i;
                $usados[$i] = true;
            }
        }

        foreach ($headers as $i => $h) {
            if (isset($usados[$i])) {
                continue;
            }
            $conta = $this->casarConta((string) $h, $contas);
            if ($conta) {
                $chave = "conta_" . $conta->id;
                if (!isset($campos[$chave])) {
                    $campos[$chave] = (int) $i;
                    $usados[$i] = true;
                }
            }
        }

        foreach ($norm as $i => $h) {
            if (isset($usados[$i])) {
                continue;
            }
            if (!isset($campos[PlanilhaImportacaoService::DEST_DESCRICAO]) && $this->pareceDescricao($h)) {
                $campos[PlanilhaImportacaoService::DEST_DESCRICAO] = (int) $i;
                $usados[$i] = true;
            }
        }

        $temContaN = false;
        foreach (array_keys($campos) as $dest) {
            if (str_starts_with((string) $dest, "conta_")) {
                $temContaN = true;
                break;
            }
        }

        if (!$temContaN) {
            foreach ($norm as $i => $h) {
                if (isset($usados[$i])) {
                    continue;
                }
                if (!isset($campos[PlanilhaImportacaoService::DEST_VALOR]) && $this->pareceValor($h)) {
                    $campos[PlanilhaImportacaoService::DEST_VALOR] = (int) $i;
                    $usados[$i] = true;
                }
            }
        }

        return ["campos" => $campos, "periodos_matriz" => $periodos];
    }

    /**
     * Mantém o que o usuário (ou o motor) já preencheu. Só entra chave nova e coluna livre.
     *
     * @param array<string,int> $atual
     * @param array<string,int> $novo
     * @return array<string,int>
     */
    public function mesclarCampos(array $atual, array $novo): array
    {
        $colunasUsadas = array_flip(array_map("intval", array_values($atual)));
        foreach ($novo as $dest => $indice) {
            $dest   = (string) $dest;
            $indice = (int) $indice;
            if (isset($atual[$dest])) {
                continue;
            }
            if (isset($colunasUsadas[$indice])) {
                continue;
            }
            $atual[$dest] = $indice;
            $colunasUsadas[$indice] = true;
        }
        return $atual;
    }

    /**
     * @param array<int,int> $atual
     * @param array<int,int> $novo
     * @param array<string,int> $campos
     * @return array<int,int>
     */
    public function mesclarPeriodos(array $atual, array $novo, array $campos): array
    {
        $usadas = array_flip(array_map("intval", array_values($campos)));
        foreach ($atual as $i) {
            $usadas[(int) $i] = true;
        }
        $out = array_map("intval", $atual);
        foreach ($novo as $i) {
            $i = (int) $i;
            if (isset($usadas[$i])) {
                continue;
            }
            $out[] = $i;
            $usadas[$i] = true;
        }
        return array_values(array_unique($out));
    }

    /**
     * @param array<int,object> $contas
     */
    public function casarConta(string $header, array $contas): ?object
    {
        $direto = DreConta::casarEmLista($contas, $header);
        if ($direto) {
            return $direto;
        }
        $canonico = $this->aliasParaConta($this->normalizar($header));
        if ($canonico !== null) {
            return DreConta::casarEmLista($contas, $canonico);
        }
        return null;
    }

    public function aliasParaConta(string $n): ?string
    {
        $mapa = [
            "receitaporproduto"         => "Receita Bruta",
            "receitabruta"              => "Receita Bruta",
            "receita"                   => "Receita Bruta",
            "receitaliquida"            => "Receita Bruta",
            "ingresos"                  => "Receita Bruta",
            "ingresosporproducto"       => "Receita Bruta",
            "ingresosporproductos"      => "Receita Bruta",
            "valortotal"                => "Receita Bruta",
            "preciodecost"              => "CMV",
            "precodecost"               => "CMV",
            "precodecusto"              => "CMV",
            "precodecompra"             => "CMV",
            "custodeenvio"              => "Tarifas de Envio",
            "custo"                     => "CMV",
            "custos"                    => "CMV",
            "cmv"                       => "CMV",
            "tarifadevendaeimpostos"    => "Tarifa de venda e impostos",
            "tarifadevenda"             => "Tarifa de venda e impostos",
            "tarifasdeventa"            => "Tarifa de venda e impostos",
            "cargoporserviciodeventa"   => "Tarifa de venda e impostos",
            "taxas"                     => "Tarifa de venda e impostos",
            "impostos"                  => "Tarifa de venda e impostos",
            "receitaporenvio"           => "Receita por envio",
            "ingresoporenvio"           => "Receita por envio",
            "tarifasdeenvio"            => "Tarifas de Envio",
            "costoenvio"                => "Tarifas de Envio",
            "fretes"                    => "Tarifas de Envio",
            "cupom"                     => "Cupons e Descontos",
            "cupons"                    => "Cupons e Descontos",
            "descuentos"                => "Cupons e Descontos",
            "descontos"                 => "Cupons e Descontos",
            "cancelamentoseereembolsos" => "Cupons e Descontos",
            "cancelamentosereembolsos"  => "Cupons e Descontos",
            "reembolsos"                => "Cupons e Descontos",
            "comissaoafiliado"          => "Comissão de Afiliados",
            "comissoes"                 => "Comissão de Afiliados",
            "comision"                  => "Comissão de Afiliados",
            "freteentregadireta"        => "Frete Entrega Direta",
            "folhaadministrativa"       => "Folha administrativa",
            "aluguel"                   => "Aluguel",
            "marketing"                 => "Marketing",
            "despesasoperacionais"      => "Despesas Operacionais",
            "depreciacao"               => "Depreciação",
            "resultadofinanceiro"       => "Resultado Financeiro",
            "caixaeequivalentes"        => "Caixa e Equivalentes",
            "contasareceber"            => "Contas a Receber",
            "estoques"                  => "Estoques",
            "fornecedores"              => "Fornecedores",
        ];
        if (isset($mapa[$n])) {
            return $mapa[$n];
        }
        foreach ($mapa as $chave => $conta) {
            if (strlen($chave) >= 10 && (str_contains($n, $chave) || str_contains($chave, $n))) {
                return $conta;
            }
        }
        return null;
    }

    public function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), "UTF-8");
        $ascii = @iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $texto);
        $texto = is_string($ascii) ? $ascii : $texto;
        return (string) preg_replace("/[^a-z0-9]+/", "", $texto);
    }

    public function parecePeriodo(string $n): bool
    {
        return in_array($n, [
            "periodo", "competencia", "data", "datavenda", "datadavenda", "mes", "ano",
            "fecha", "fechaventa", "fechadeventa", "date", "orderdate",
        ], true)
            || str_contains($n, "periodo")
            || str_contains($n, "competencia")
            || str_contains($n, "datavenda")
            || str_contains($n, "datadavenda")
            || str_contains($n, "fechadeventa")
            || str_contains($n, "fechaventa");
    }

    public function pareceConta(string $n): bool
    {
        return in_array($n, [
            "conta", "contaplano", "nomedaconta", "descricaodaconta",
            "classificacao", "rubrica",
        ], true) || (str_starts_with($n, "conta") && !str_contains($n, "contato") && !str_contains($n, "contas"));
    }

    public function pareceValor(string $n): bool
    {
        return in_array($n, [
            "valor", "valortotal", "vlr", "amount", "valorrs", "total",
        ], true)
            || str_contains($n, "valortotal");
    }

    public function pareceDescricao(string $n): bool
    {
        if ($this->aliasParaConta($n) !== null || $this->pareceValor($n) || $this->parecePeriodo($n)) {
            return false;
        }
        return in_array($n, [
            "descricao", "historico", "observacao", "obs", "detalhe", "produto",
            "item", "nomeproduto", "titulo", "titulodoanuncio", "titulodelapublicacion",
            "title", "publicacion", "anuncio",
        ], true)
            || str_starts_with($n, "titulo")
            || $n === "nomeproduto"
            || $n === "titulodoanuncio";
    }

    public function pareceUnidade(string $n): bool
    {
        return in_array($n, ["unidade", "centrocusto", "cc", "filial", "loja", "marketplace", "canal", "empresa"], true);
    }

    public function pareceCabecalhoPeriodo(string $n): bool
    {
        return (bool) preg_match("/^(ano\d+|20\d{2}|jan|fev|mar|abr|mai|jun|jul|ago|set|out|nov|dez)/", $n);
    }
}
