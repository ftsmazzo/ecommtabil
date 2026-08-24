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
        $canonico = $this->aliasParaConta($this->normalizar($header));
        if ($canonico !== null) {
            $porAlias = DreConta::casarEmLista($contas, $canonico);
            if ($porAlias) {
                return $porAlias;
            }
        }
        return DreConta::casarEmLista($contas, $header);
    }

    public function aliasParaConta(string $n): ?string
    {
        $mapa = $this->aliasMapa();
        if (isset($mapa[$n])) {
            return $mapa[$n];
        }
        uksort($mapa, static fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));
        foreach ($mapa as $chave => $conta) {
            if (strlen((string) $chave) >= 8 && str_contains($n, (string) $chave)) {
                return $conta;
            }
        }
        return null;
    }

    /**
     * Cabeçalhos do modelo SAGA DRE Vendas + ML/Shopee → conta canônica.
     *
     * @return array<string,string>
     */
    public function aliasMapa(): array
    {
        return [
            "receitaporproduto"         => "Receita Bruta",
            "receitaporproductos"       => "Receita Bruta",
            "receitabruta"              => "Receita Bruta",
            "receitabrutadevendas"      => "Receita Bruta",
            "ingresosporproducto"       => "Receita Bruta",
            "ingresosporproductos"      => "Receita Bruta",
            "ingresoporproducto"        => "Receita Bruta",
            "preciodecost"              => "CMV",
            "preciodecosto"             => "CMV",
            "precodecost"               => "CMV",
            "precodecusto"              => "CMV",
            "precodecompra"             => "CMV",
            "costodoproduto"            => "CMV",
            "custodoproduto"            => "CMV",
            "custodeenvio"              => "Tarifas de Envio",
            "costodeenvio"              => "Tarifas de Envio",
            "custo"                     => "CMV",
            "custos"                    => "CMV",
            "costo"                     => "CMV",
            "cmv"                       => "CMV",
            "tarifadevendaeimpostos"    => "Tarifa de venda e impostos",
            "tarifadeventa"             => "Tarifa de venda e impostos",
            "tarifadevenda"             => "Tarifa de venda e impostos",
            "tarifasdeventa"            => "Tarifa de venda e impostos",
            "cargoporserviciodeventa"   => "Tarifa de venda e impostos",
            "cargoporservicio"          => "Tarifa de venda e impostos",
            "receitaporenvio"           => "Receita por envio",
            "ingresoporenvio"           => "Receita por envio",
            "fretecomprador"            => "Receita por envio",
            "tarifasdeenvio"            => "Tarifas de Envio",
            "fretevendedor"             => "Tarifas de Envio",
            "costoenvio"                => "Tarifas de Envio",
            "cupom"                     => "Cupons e Descontos",
            "cuponsedescontos"          => "Cupons e Descontos",
            "descuentos"                => "Cupons e Descontos",
            "cancelamentoseereembolsos" => "Cupons e Descontos",
            "cancelamentosereembolsos"  => "Cupons e Descontos",
            "reembolsos"                => "Cupons e Descontos",
            "comissaoafiliado"          => "Comissão de Afiliados",
            "comissaodeafiliados"       => "Comissão de Afiliados",
            "freteentregadireta"        => "Frete Entrega Direta",
            "deducoes"                  => "Deduções",
        ];
    }

    /**
     * O que cada campo do modelo significa e quando precisa estar preenchido.
     * Fonte: SAGA_Modelo_DRE_Vendas (Instruções).
     *
     * @return array{label:string,hint:string,exemplos:string,obrigatorio:bool,esperado:bool}
     */
    public function guiaPorNomeConta(string $nome): array
    {
        $n = $this->normalizar($nome);
        $guias = [
            "receitabruta" => [
                "label" => "Receita Bruta",
                "hint" => "Valor bruto do produto (positivo). Obrigatório numa planilha de vendas.",
                "exemplos" => "Receita por Produto · Ingresos por producto",
                "obrigatorio" => false,
                "esperado" => true,
            ],
            "receitaporenvio" => [
                "label" => "Receita por envio",
                "hint" => "Frete que o comprador pagou (positivo). Vazio se a planilha não tiver essa coluna.",
                "exemplos" => "Receita por envio · Ingreso por envío · Frete Comprador",
                "obrigatorio" => false,
                "esperado" => true,
            ],
            "deducoes" => [
                "label" => "Deduções",
                "hint" => "Impostos/devoluções agregados. No marketplace costuma ficar vazio (a tarifa já entra na conta de tarifa).",
                "exemplos" => "Deduções",
                "obrigatorio" => false,
                "esperado" => false,
            ],
            "tarifadevendaeimpostos" => [
                "label" => "Tarifa de venda e impostos",
                "hint" => "Comissão/tarifa do marketplace, em geral negativa no arquivo do ML.",
                "exemplos" => "Tarifa de venda e impostos · Cargo por servicio de venta",
                "obrigatorio" => false,
                "esperado" => true,
            ],
            "cuponsedescontos" => [
                "label" => "Cupons e Descontos",
                "hint" => "Cupom, cancelamento e reembolso (em geral negativo).",
                "exemplos" => "Cupom · Cancelamentos e Reembolsos · Descuentos",
                "obrigatorio" => false,
                "esperado" => true,
            ],
            "cmv" => [
                "label" => "CMV (custo da mercadoria)",
                "hint" => "Custo do produto vendido (positivo no modelo SAGA).",
                "exemplos" => "Custo · Precio de cost · Precio de costo",
                "obrigatorio" => false,
                "esperado" => true,
            ],
            "despesasoperacionais" => [
                "label" => "Despesas Operacionais",
                "hint" => "Despesa fixa. Planilha de venda ML/Shopee normalmente deixa vazio.",
                "exemplos" => "Despesas operacionais",
                "obrigatorio" => false,
                "esperado" => false,
            ],
            "folhaadministrativa" => [
                "label" => "Folha administrativa",
                "hint" => "Salários. Não vem na planilha de vendas do marketplace — vazio é válido.",
                "exemplos" => "Folha",
                "obrigatorio" => false,
                "esperado" => false,
            ],
            "aluguel" => [
                "label" => "Aluguel",
                "hint" => "Despesa fixa. Não vem na planilha de vendas — vazio é válido.",
                "exemplos" => "Aluguel",
                "obrigatorio" => false,
                "esperado" => false,
            ],
            "marketing" => [
                "label" => "Marketing",
                "hint" => "Ads/ads do marketplace só se houver coluna própria. Senão, vazio é válido.",
                "exemplos" => "Marketing · Ads",
                "obrigatorio" => false,
                "esperado" => false,
            ],
            "tarifasdeenvio" => [
                "label" => "Tarifas de Envio",
                "hint" => "Custo de frete do vendedor (em geral negativo no ML).",
                "exemplos" => "Tarifas de Envio · Costo envío · Frete Vendedor",
                "obrigatorio" => false,
                "esperado" => true,
            ],
            "comissaodeafiliados" => [
                "label" => "Comissão de Afiliados",
                "hint" => "Comissão de afiliado do marketplace, se existir no arquivo.",
                "exemplos" => "Comissão Afiliado",
                "obrigatorio" => false,
                "esperado" => true,
            ],
            "freteentregadireta" => [
                "label" => "Frete Entrega Direta",
                "hint" => "Frete de entrega direta. Vazio se o cliente não tiver essa coluna.",
                "exemplos" => "Frete Entrega Direta",
                "obrigatorio" => false,
                "esperado" => true,
            ],
            "depreciacao" => [
                "label" => "Depreciação",
                "hint" => "Não vem em planilha de vendas. Vazio é válido.",
                "exemplos" => "Depreciação",
                "obrigatorio" => false,
                "esperado" => false,
            ],
            "resultadofinanceiro" => [
                "label" => "Resultado Financeiro",
                "hint" => "Juros/variação cambial. Não vem em planilha de vendas. Vazio é válido.",
                "exemplos" => "Resultado financeiro",
                "obrigatorio" => false,
                "esperado" => false,
            ],
        ];
        if (isset($guias[$n])) {
            return $guias[$n];
        }
        return [
            "label" => $nome,
            "hint" => "Só preencha se a planilha do cliente tiver uma coluna de valor para esta conta. Caso contrário deixe vazio — isso é válido.",
            "exemplos" => "",
            "obrigatorio" => false,
            "esperado" => false,
        ];
    }

    /**
     * @param array{destino:string,label:string,hint:string,grupo:string,busca:string} $campo
     * @return array{destino:string,label:string,hint:string,grupo:string,busca:string,exemplos:string,obrigatorio:bool,esperado:bool}
     */
    public function enriquecerCampo(array $campo): array
    {
        $dest = (string) ($campo["destino"] ?? "");
        $label = trim((string) ($campo["label"] ?? ""));
        $labelNorm = $this->normalizar($label);

        if (in_array($labelNorm, ["campopadrao", "padrao", "campo", ""], true) || str_contains($labelNorm, "campopadrao")) {
            $label = "";
        }

        if ($dest === PlanilhaImportacaoService::DEST_PERIODO) {
            $campo["label"] = $label !== "" ? $label : "Data da venda";
            $campo["hint"] = "Data em que a venda aconteceu. O sistema agrupa por mês. Obrigatório.";
            $campo["exemplos"] = "Data da Venda · Fecha de venta";
            $campo["obrigatorio"] = true;
            $campo["esperado"] = true;
        } elseif ($dest === PlanilhaImportacaoService::DEST_DESCRICAO) {
            $campo["label"] = $label !== "" ? $label : "Descrição / título do anúncio";
            $campo["hint"] = "Texto do produto ou anúncio. Não é valor. Recomendado, não bloqueia o processamento.";
            $campo["exemplos"] = "Produto · Título de la publicación · Título do anúncio";
            $campo["obrigatorio"] = false;
            $campo["esperado"] = true;
        } elseif ($dest === PlanilhaImportacaoService::DEST_UNIDADE) {
            $campo["label"] = $label !== "" ? $label : "Marketplace / empresa";
            $campo["hint"] = "Canal ou loja. Opcional.";
            $campo["exemplos"] = "Marketplace · Empresa";
            $campo["obrigatorio"] = false;
            $campo["esperado"] = true;
        } elseif (preg_match('/^conta_/', $dest)) {
            $nome = $label;
            if (str_contains($nome, "—")) {
                $nome = trim((string) explode("—", $nome, 2)[1]);
            }
            $guia = $this->guiaPorNomeConta($nome);
            $campo["label"] = $guia["label"];
            $campo["hint"] = $guia["hint"];
            $campo["exemplos"] = $guia["exemplos"];
            $campo["obrigatorio"] = $guia["obrigatorio"];
            $campo["esperado"] = $guia["esperado"];
        } else {
            $campo["exemplos"] = (string) ($campo["exemplos"] ?? "");
            $campo["obrigatorio"] = (bool) ($campo["obrigatorio"] ?? false);
            $campo["esperado"] = (bool) ($campo["esperado"] ?? false);
        }

        $campo["busca"] = mb_strtolower(
            ($campo["label"] ?? "") . " " . ($campo["exemplos"] ?? "") . " " . $dest,
            "UTF-8"
        );
        return $campo;
    }

    public function dicaPromptIa(): string
    {
        return <<<TXT
Dicionário do modelo SAGA (planilha de vendas marketplace):
- Data da Venda → __periodo__
- Produto / Título → __descricao__
- Empresa / Marketplace → __unidade__
- Receita por Produto / Ingresos por producto → Receita Bruta
- Custo / Precio de cost → CMV
- Tarifa de venda e impostos / Cargo por servicio de venta → Tarifa de venda e impostos
- Receita por envio / Frete Comprador → Receita por envio
- Tarifas de Envio / Frete Vendedor / Costo envío → Tarifas de Envio
- Cupom / Cancelamentos e Reembolsos → Cupons e Descontos
- Comissão Afiliado → Comissão de Afiliados
- Frete Entrega Direta → Frete Entrega Direta
NÃO mapear: Nº de venda, SKU, Estado, Depósito, Unidades, Total (o Total do modelo SAGA é calculado e não entra no DRE).
Folha, Aluguel, Marketing, Depreciação: só mapeie se existir coluna com esse sentido. Vazio é válido.
TXT;
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
            "fecha", "fechaventa", "fechadeventa", "date", "orderdate", "datadecriacao",
        ], true)
            || str_contains($n, "periodo")
            || str_contains($n, "competencia")
            || str_contains($n, "datavenda")
            || str_contains($n, "datadavenda")
            || str_contains($n, "fechadeventa")
            || str_contains($n, "fechaventa")
            || str_contains($n, "datadecriacao");
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
            "title", "publicacion", "anuncio", "nombredelproducto", "nombredelarticulo",
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
