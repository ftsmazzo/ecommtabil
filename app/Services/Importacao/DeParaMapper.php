<?php

namespace App\Services\Importacao;

use App\Models\DreConta;

/**
 * Motor slot-first: para cada campo do demonstrativo, escolhe 0 ou 1 coluna.
 * A planilha é evidência. Famílias de origem só detectam layout (matriz vs ledger).
 */
class DeParaMapper
{
    /**
     * @param array<int,string> $headers
     * @param array<int,object> $contas
     * @param array<int,array<int,string>> $previews
     * @return array{campos: array<string,int>, periodos_matriz: array<int,int>}
     */
    public function sugerir(array $headers, string $layout, array $contas = [], array $previews = []): array
    {
        $campos   = [];
        $periodos = [];
        $norm     = array_map([$this, "normalizar"], $headers);

        if ($layout === PlanilhaImportacaoService::LAYOUT_MATRIZ) {
            $usados = [];
            $melhorData = $this->melhorIndicePeriodo($norm);
            if ($melhorData !== null) {
                $campos[PlanilhaImportacaoService::DEST_PERIODO] = $melhorData;
                $usados[$melhorData] = true;
            }
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

        return [
            "campos"          => $this->atribuirDoModelo($headers, $norm, $previews, $contas),
            "periodos_matriz" => [],
        ];
    }

    /**
     * Percorre os slots do plano. Conta sem coluna parecida fica vazia (válido).
     *
     * @param array<int,string> $headers
     * @param array<int,string> $norm
     * @param array<int,array<int,string>> $previews
     * @param array<int,object> $contas
     * @return array<string,int>
     */
    public function atribuirDoModelo(array $headers, array $norm, array $previews, array $contas): array
    {
        $classes = [];
        foreach ($headers as $i => $h) {
            $i = (int) $i;
            $n = $this->chaveCabecalho((string) ($norm[$i] ?? $h));
            $amostras = array_values(array_filter(array_map("strval", (array) ($previews[$i] ?? []))));
            $classes[$i] = [
                "n"        => $n,
                "tipo"     => $this->classificarColuna($n, $amostras),
                "amostras" => $amostras,
            ];
        }

        $alvos = [];
        $alvos[] = [
            "dest"  => PlanilhaImportacaoService::DEST_DESCRICAO,
            "tipo"  => "texto",
            "nomes" => ["titulodoanuncio", "titulodelapublicacion", "titulodapublicacion", "nomedoproduto", "nomedoitem", "nomeproduto", "descricaoml", "descricao"],
            "nao"   => ["status", "sku", "custo", "url", "cidade"],
        ];
        $alvos[] = [
            "dest"  => PlanilhaImportacaoService::DEST_UNIDADE,
            "tipo"  => "texto",
            "nomes" => ["canaldevenda", "canaldevendas", "marketplace"],
            "nao"   => ["cidade", "pais"],
        ];

        foreach ($contas as $conta) {
            $nome = trim((string) $conta->nome);
            $conceito = $this->conceitoDaConta($nome);
            $nomes = [$this->chaveCabecalho($nome)];
            $nao = [];
            if ($conceito !== null) {
                $nomes = array_values(array_unique(array_merge($nomes, $conceito["quer"])));
                $nao = $conceito["nao"];
            }
            $alvos[] = [
                "dest"  => "conta_" . (int) $conta->id,
                "tipo"  => "dinheiro",
                "nomes" => $nomes,
                "nao"   => $nao,
                "conta" => $nome,
            ];
        }

        $pares = [];
        foreach ($alvos as $alvo) {
            foreach ($classes as $i => $col) {
                if (!$this->colunaServe($alvo["tipo"], $col["tipo"])) {
                    continue;
                }
                $nota = $this->notaToken($alvo["nomes"], $alvo["nao"] ?? [], $col["n"]);
                $alias = $this->aliasParaConta($col["n"]);
                if ($alias !== null && isset($alvo["conta"]) && strcasecmp($alias, (string) $alvo["conta"]) === 0) {
                    $nota = max($nota, 100);
                }
                if ($nota < 85) {
                    continue;
                }
                $pares[] = ["dest" => $alvo["dest"], "col" => $i, "nota" => $nota];
            }
        }

        usort($pares, static fn ($a, $b) => $b["nota"] <=> $a["nota"]);
        $campos = [];
        $usadas = [];
        foreach ($pares as $p) {
            if (isset($campos[$p["dest"]]) || isset($usadas[$p["col"]])) {
                continue;
            }
            $campos[$p["dest"]] = $p["col"];
            $usadas[$p["col"]] = true;
        }

        // Data: ranking próprio (pagamento/criação/venda). Nunca prevista de envio / prazo.
        $melhorData = null;
        $melhorNota = 0;
        foreach ($classes as $i => $col) {
            if (isset($usadas[$i])) {
                continue;
            }
            if ($col["tipo"] !== "data" && $col["tipo"] !== "vazio") {
                continue;
            }
            $nota = $this->notaPeriodo($col["n"]);
            if ($nota > $melhorNota) {
                $melhorNota = $nota;
                $melhorData = (int) $i;
            }
        }
        if ($melhorData !== null && $melhorNota >= 40) {
            $campos[PlanilhaImportacaoService::DEST_PERIODO] = $melhorData;
        }

        return $campos;
    }

    /**
     * @param array<string,int> $campos
     * @param array<int,string> $headers
     * @param array<int,array<int,string>> $previews
     */
    public function mapaCompativel(array $campos, array $headers, array $previews): bool
    {
        foreach ($campos as $dest => $indice) {
            $indice = (int) $indice;
            $n = $this->chaveCabecalho((string) ($headers[$indice] ?? ""));
            $amostras = array_values(array_filter(array_map("strval", (array) ($previews[$indice] ?? []))));
            $tipoCol = $this->classificarColuna($n, $amostras);
            if (!$this->colunaServe($this->tipoEsperado((string) $dest), $tipoCol)) {
                return false;
            }
            if ((string) $dest === PlanilhaImportacaoService::DEST_PERIODO && $this->notaPeriodo($n) < 40) {
                return false;
            }
        }
        return true;
    }

    public function tipoEsperado(string $dest): string
    {
        if ($dest === PlanilhaImportacaoService::DEST_PERIODO) {
            return "data";
        }
        if ($dest === PlanilhaImportacaoService::DEST_DESCRICAO || $dest === PlanilhaImportacaoService::DEST_UNIDADE) {
            return "texto";
        }
        return "dinheiro";
    }

    public function colunaServe(string $esperado, string $tipoCol): bool
    {
        if ($tipoCol === "ignorar") {
            return false;
        }
        if ($esperado === "data") {
            return $tipoCol === "data";
        }
        if ($esperado === "dinheiro") {
            return $tipoCol === "dinheiro";
        }
        return $tipoCol === "texto" || $tipoCol === "vazio";
    }

    /**
     * @param array<int,string> $quer
     * @param array<int,string> $nao
     */
    public function notaToken(array $quer, array $nao, string $n): int
    {
        if ($n === "") {
            return 0;
        }
        foreach ($nao as $neg) {
            $neg = $this->chaveCabecalho((string) $neg);
            if ($neg === "") {
                continue;
            }
            if ($n === $neg || (strlen($neg) >= 4 && str_contains($n, $neg))) {
                return 0;
            }
        }
        $melhor = 0;
        foreach ($quer as $q) {
            $q = $this->chaveCabecalho((string) $q);
            if ($q === "" || strlen($q) < 4) {
                continue;
            }
            if ($n === $q) {
                return 100;
            }
            if (str_starts_with($n, $q) || str_starts_with($q, $n)) {
                $melhor = max($melhor, 96);
                continue;
            }
            if (str_contains($n, $q) || (strlen($n) >= 4 && str_contains($q, $n))) {
                $melhor = max($melhor, 92);
            }
        }
        return $melhor;
    }

    public function chaveCabecalho(string $texto): string
    {
        $n = $this->normalizar($texto);
        $n = preg_replace("/(en)?(brl|usd|ars|mxn|cop|eur|rs)$/", "", $n) ?? $n;
        return (string) $n;
    }

    /**
     * @param array<int,string> $amostras
     */
    public function classificarColuna(string $n, array $amostras): string
    {
        foreach ([
            "sku", "nvenda", "nodevenda", "ndevenda", "numerodevenda", "numerovenda", "id", "pack", "kit",
            "cidade", "estado", "pais", "uf", "cep", "cpf", "cnpj", "telefone",
            "url", "acompanhamento", "rastreio", "tracking", "nfe", "anexo",
            "unidades", "quantidade", "variacao", "pedido", "serie", "lucro", "vendaliq",
        ] as $lixo) {
            if ($n === $lixo || str_starts_with($n, $lixo) || (strlen($lixo) >= 5 && str_contains($n, $lixo))) {
                return "ignorar";
            }
        }
        if (str_contains($n, "pertence") || str_contains($n, "host") || $n === "status" || str_contains($n, "statusdo")) {
            return "ignorar";
        }
        $tipo = $this->tipoAmostras($amostras);
        if ($tipo === "url") {
            return "ignorar";
        }
        if ($this->notaPeriodo($n) >= 80 || $tipo === "data") {
            return "data";
        }
        if ($tipo === "numero") {
            return "dinheiro";
        }
        if ($tipo === "vazio") {
            return "vazio";
        }
        return "texto";
    }

    /**
     * @param array<int,string> $nomesModelo
     */
    public function notaPorNome(array $nomesModelo, string $cabecalho, string $tipoAlvo, string $tipoCol): int
    {
        if ($cabecalho === "") {
            return 0;
        }
        $melhor = 0;
        foreach ($nomesModelo as $nome) {
            $nome = $this->chaveCabecalho((string) $nome);
            if ($nome === "" || strlen($nome) < 4) {
                continue;
            }
            if ($cabecalho === $nome || str_starts_with($cabecalho, $nome) || str_starts_with($nome, $cabecalho)) {
                $melhor = max($melhor, 100);
                continue;
            }
            if (strlen($nome) >= 8 && str_contains($cabecalho, $nome)) {
                $melhor = max($melhor, 92);
                continue;
            }
            similar_text($cabecalho, $nome, $pct);
            $melhor = max($melhor, (int) round($pct));
        }
        if ($tipoAlvo === $tipoCol) {
            $melhor += 8;
        }
        return $melhor;
    }

    /**
     * @return array{tipo:string,quer:array<int,string>,nao:array<int,string>}|null
     */
    public function conceitoDaConta(string $nome): ?array
    {
        $n = $this->normalizar($nome);
        $conceitos = [
            "receitabruta" => [
                "tipo" => "dinheiro",
                "quer" => ["receitabruta", "receitaporproduto", "ingresosporproducto", "ingresosporproductos", "valortotaldoproduto", "precoacordado", "vrvenda", "valorvenda"],
                "nao"  => ["envio", "frete", "tarifa", "taxa", "imposto", "cancelamento", "reembolso", "acrescimo", "parcelamento"],
            ],
            "receitaporenvio" => [
                "tipo" => "dinheiro",
                "quer" => ["receitaporenvio", "ingresoporenvio", "ingresosporenvio", "fretecomprador"],
                "nao"  => ["tarifasdeenvio", "custoenvio"],
            ],
            "cmv" => [
                "tipo" => "dinheiro",
                "quer" => ["cmv", "preciodecost", "preciodecosto", "precodecusto", "custodoproduto", "precodecompra", "preciodecompra", "custo"],
                "nao"  => ["envio", "frete", "tarifa", "receita"],
            ],
            "tarifasdeenvio" => [
                "tipo" => "dinheiro",
                "quer" => ["tarifasdeenvio", "tarifadeenvio", "frete"],
                "nao"  => ["receitaporenvio", "pagopelocomprador", "url", "acompanhamento", "troca"],
            ],
            "tarifadevendaeimpostos" => [
                "tipo" => "dinheiro",
                "quer" => ["tarifadevendaeimpostos", "tarifadevenda", "tarifadeventa", "cargoporserviciodeventa", "taxadecomissao", "tarifaml"],
                "nao"  => ["parcelamento", "pais", "cidade", "url"],
            ],
            "cuponsedescontos" => [
                "tipo" => "dinheiro",
                "quer" => ["cuponsedescontos", "cancelamentoseereembolsos", "cancelamentosereembolsos", "descontosbonus", "descontodovendedor", "desconto"],
                "nao"  => ["nfe", "anexo", "url"],
            ],
            "comissaodeafiliados" => [
                "tipo" => "dinheiro",
                "quer" => ["comissaodeafiliados", "comissaoafiliado", "valorafiliado"],
                "nao"  => [],
            ],
            "freteentregadireta" => [
                "tipo" => "dinheiro",
                "quer" => ["freteentregadireta"],
                "nao"  => [],
            ],
            "deducoes" => [
                "tipo" => "dinheiro",
                "quer" => ["deducoes", "imposto"],
                "nao"  => ["tarifadevenda"],
            ],
        ];
        return $conceitos[$n] ?? null;
    }

    /**
     * @param array{dest:string,tipo:string,quer:array,nao:array} $alvo
     * @param array<int,string> $amostras
     */
    public function notaConceito(array $alvo, string $n, array $amostras): int
    {
        if ($n === "") {
            return 0;
        }
        $hits = 0;
        $nota = 0;
        foreach ($alvo["quer"] as $tok) {
            $tok = $this->normalizar((string) $tok);
            if ($tok === "") {
                continue;
            }
            if ($n === $tok || str_contains($n, $tok) || (strlen($tok) >= 6 && str_contains($tok, $n))) {
                $hits++;
                $nota += 12 + min(8, strlen($tok));
            }
        }
        foreach ($alvo["nao"] as $tok) {
            $tok = $this->normalizar((string) $tok);
            if ($tok !== "" && str_contains($n, $tok)) {
                $nota -= 40;
            }
        }

        $tipoAmostra = $this->tipoAmostras($amostras);
        if ($hits === 0) {
            if ($alvo["tipo"] === "data" && $tipoAmostra === "data") {
                return max(0, $this->notaPeriodo($n) + 40);
            }
            return 0;
        }
        if ($alvo["tipo"] === "data") {
            $nota += ($tipoAmostra === "data") ? 40 : ($tipoAmostra === "vazio" ? 0 : -25);
            $nota += $this->notaPeriodo($n);
        } elseif ($alvo["tipo"] === "dinheiro") {
            $nota += ($tipoAmostra === "numero") ? 28 : ($tipoAmostra === "vazio" ? 0 : -20);
        } elseif ($alvo["tipo"] === "texto") {
            $nota += ($tipoAmostra === "texto") ? 18 : ($tipoAmostra === "numero" ? -15 : 0);
        }

        return $nota;
    }

    /**
     * @param array<int,string> $amostras
     */
    public function tipoAmostras(array $amostras): string
    {
        $datas = 0;
        $nums  = 0;
        $textos = 0;
        $n = 0;
        foreach (array_slice($amostras, 0, 5) as $a) {
            $a = trim((string) $a);
            if ($a === "" || $a === "-") {
                continue;
            }
            $n++;
            if (preg_match('#^https?://#i', $a) || str_contains(mb_strtolower($a), "http")) {
                return "url";
            }
            if ($this->pareceAmostraData($a)) {
                $datas++;
                continue;
            }
            if ($this->pareceAmostraNumero($a)) {
                $nums++;
                continue;
            }
            $textos++;
        }
        if ($n === 0) {
            return "vazio";
        }
        if ($datas >= $n / 2) {
            return "data";
        }
        if ($nums >= $n / 2) {
            return "numero";
        }
        return "texto";
    }

    public function pareceAmostraData(string $v): bool
    {
        if (preg_match('/^\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}/', $v)) {
            return true;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $v)) {
            return true;
        }
        if (preg_match('/^\d{1,2}\s+de\s+\S+/u', mb_strtolower($v))) {
            return true;
        }
        return false;
    }

    public function pareceAmostraNumero(string $v): bool
    {
        $s = str_replace(["R$", " ", "\xc2\xa0"], "", $v);
        $s = str_replace(".", "", $s);
        $s = str_replace(",", ".", $s);
        return is_numeric($s);
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
     * IA manda no slot que ela preencheu; o motor só completa o que ela omitiu.
     *
     * @param array<string,int> $motor
     * @param array<string,int> $ia
     * @return array<string,int>
     */
    public function aplicarRevisaoIa(array $motor, array $ia): array
    {
        $campos = [];
        $usadas = [];
        foreach ($ia as $dest => $indice) {
            $dest   = (string) $dest;
            $indice = (int) $indice;
            if ($dest === "" || isset($usadas[$indice])) {
                continue;
            }
            $campos[$dest] = $indice;
            $usadas[$indice] = true;
        }
        foreach ($motor as $dest => $indice) {
            $dest   = (string) $dest;
            $indice = (int) $indice;
            if (isset($campos[$dest]) || isset($usadas[$indice])) {
                continue;
            }
            $campos[$dest] = $indice;
            $usadas[$indice] = true;
        }
        return $campos;
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
        $n = $this->normalizar($header);
        $canonico = $this->aliasPorFragmento($n) ?? $this->aliasParaConta($n);
        if ($canonico !== null) {
            $porAlias = DreConta::casarEmLista($contas, $canonico);
            if ($porAlias) {
                return $porAlias;
            }
        }
        return DreConta::casarEmLista($contas, $header);
    }

    /**
     * Regras por pedaço do cabeçalho (Shopee BR, ML ES/PT). Ordem = mais específico primeiro.
     */
    public function aliasPorFragmento(string $n): ?string
    {
        if ($n === "" || $this->pareceIgnorar($n)) {
            return null;
        }
        $regras = [
            ["taxadecomissaodeafiliado", "Comissão de Afiliados"],
            ["comissaodeafiliado", "Comissão de Afiliados"],
            ["taxadecomissao", "Tarifa de venda e impostos"],
            ["taxadeservico", "Tarifa de venda e impostos"],
            ["taxadetransacao", "Tarifa de venda e impostos"],
            ["taxadecomiss", "Tarifa de venda e impostos"],
            ["valortotaldoproduto", "Receita Bruta"],
            ["precoacordado", "Receita Bruta"],
            ["precodoitem", "Receita Bruta"],
            ["subtotaldoproduto", "Receita Bruta"],
            ["custodeenviopagopelocomprador", "Receita por envio"],
            ["enviopagopelocomprador", "Receita por envio"],
            ["fretepagopelocomprador", "Receita por envio"],
            ["estimativadefrete", "Tarifas de Envio"],
            ["reembolsodefrete", "Tarifas de Envio"],
            ["descontodovendedor", "Cupons e Descontos"],
            ["descontodoshopee", "Cupons e Descontos"],
            ["descontoshopee", "Cupons e Descontos"],
            ["cupomdaloja", "Cupons e Descontos"],
            ["cupomshopee", "Cupons e Descontos"],
            ["moedinhashopee", "Cupons e Descontos"],
            ["moedas shopee", "Cupons e Descontos"],
            ["cargoporserviciodeventa", "Tarifa de venda e impostos"],
            ["ingresosporproducto", "Receita Bruta"],
            ["receitaporproduto", "Receita Bruta"],
        ];
        foreach ($regras as [$frag, $conta]) {
            $frag = $this->normalizar($frag);
            if ($frag !== "" && str_contains($n, $frag)) {
                return $conta;
            }
        }
        return null;
    }

    public function pareceIgnorar(string $n): bool
    {
        if ($n === "") {
            return true;
        }
        foreach (["valor", "preco", "taxa", "receita", "custo", "frete", "cupom", "desconto", "comiss", "ingreso", "tarifa"] as $keep) {
            if (str_contains($n, $keep)) {
                return false;
            }
        }
        if ($this->notaPeriodo($n) > 0) {
            return false;
        }
        $lixo = [
            "statusdopedido", "status", "sku", "skudaloja", "quantidade", "qtd",
            "unidades", "username", "comprador", "telefone", "cpf", "cnpj", "cep",
            "cidade", "estado", "endereco", "rastreio", "tracking", "notafiscal", "motivo",
            "prazodeenvio", "metododepagamento", "metododeenvio", "variacao",
            "npedido", "nopedido", "idpedido", "orderid", "numerodopedido",
        ];
        foreach ($lixo as $x) {
            if ($n === $x || (strlen($x) >= 5 && str_contains($n, $x))) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<int,string> $norm
     */
    public function melhorIndicePeriodo(array $norm): ?int
    {
        $melhor = null;
        $melhorNota = -1;
        foreach ($norm as $i => $h) {
            $nota = $this->notaPeriodo($h);
            if ($nota > $melhorNota) {
                $melhorNota = $nota;
                $melhor = (int) $i;
            }
        }
        return $melhorNota > 0 ? $melhor : null;
    }

    public function notaPeriodo(string $n): int
    {
        if ($n === "") {
            return 0;
        }
        // Frete / prazo / conclusão: nunca viram competência do DRE.
        if (
            str_contains($n, "prevista")
            || str_contains($n, "prazo")
            || str_contains($n, "envio")
            || str_contains($n, "conclusao")
            || str_contains($n, "entreg")
            || str_contains($n, "tempo")
            || str_contains($n, "parcelamento")
        ) {
            return 0;
        }
        if (str_contains($n, "datadavenda") || str_contains($n, "fechadeventa") || $n === "datavenda") {
            return 100;
        }
        if (
            str_contains($n, "horadopagamento")
            || str_contains($n, "datapagamentodopedido")
            || str_contains($n, "datapagamento")
            || str_contains($n, "pagamentodopedido")
        ) {
            return 95;
        }
        if (str_contains($n, "datadecriacaodopedido") || str_contains($n, "datadecriacao")) {
            return 90;
        }
        if ($n === "data" || $n === "fecha" || $n === "date" || $n === "periodo" || $n === "competencia") {
            return 70;
        }
        if ($this->parecePeriodo($n)) {
            return 40;
        }
        return 0;
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
            "receitabrutadevendas"      => "Receita Bruta",
            "receitabruta"              => "Receita Bruta",
            "devolucoesecancelamentos"  => "Cupons e Descontos",
            "impostossobrevendas"       => "Deduções",
            "recebimentosdeclientes"    => "Recebimentos de Clientes",
            "pagamentosfornecedores"    => "Pagamentos a Fornecedores",
            "pagamentosfornecedor"      => "Pagamentos a Fornecedores",
            "caixaeequivalentes"        => "Caixa e Equivalentes",
            "caixaeequivalentesdecaixa" => "Caixa e Equivalentes",
            "contasareceber"            => "Contas a Receber",
            "contasareceberclientes"    => "Contas a Receber",
            "estoques"                  => "Estoques",
            "fornecedores"              => "Fornecedores",
            "emprestimoscp"             => "Empréstimos CP",
            "emprestimoslp"             => "Empréstimos LP",
            "patrimonioliquido"         => "Patrimônio Líquido",
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
                "exemplos" => "Receita por Produto · Valor total do produto · Preço acordado · Ingresos por producto",
                "obrigatorio" => false,
                "esperado" => true,
            ],
            "receitaporenvio" => [
                "label" => "Receita por envio",
                "hint" => "Frete que o comprador pagou (positivo). Vazio se a planilha não tiver essa coluna.",
                "exemplos" => "Receita por envio · Frete Comprador · Custo de envio pago pelo comprador",
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
                "exemplos" => "Tarifa de venda e impostos · Cargo por servicio · Taxa de comissão · Taxa de serviço · Taxa de transação",
                "obrigatorio" => false,
                "esperado" => true,
            ],
            "cuponsedescontos" => [
                "label" => "Cupons e Descontos",
                "hint" => "Cupom, cancelamento e reembolso (em geral negativo).",
                "exemplos" => "Cupom · Cancelamentos e Reembolsos · Cupom da loja · Desconto do vendedor · Desconto Shopee",
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
                "exemplos" => "Tarifas de Envio · Estimativa de frete · Frete Vendedor · Costo envío",
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
O modelo precisa de: data da venda, texto do produto, dinheiro de receita, custo, tarifas, fretes, descontos.
O arquivo pode ter qualquer nome de coluna. Use o sentido do cabeçalho e das amostras, não um template de marketplace.
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
            || str_contains($n, "datadecriacao")
            || str_contains($n, "datapagamento");
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
        if ($this->aliasPorFragmento($n) !== null || $this->aliasParaConta($n) !== null || $this->pareceValor($n) || $this->parecePeriodo($n)) {
            return false;
        }
        return in_array($n, [
            "descricao", "historico", "observacao", "obs", "detalhe", "produto",
            "nomeproduto", "nomedoitem", "nomedoproduto", "descricaodoitem", "descricaodoproduto",
            "titulo", "titulodoanuncio", "titulodelapublicacion",
            "title", "publicacion", "anuncio", "nombredelproducto", "nombredelarticulo",
        ], true)
            || str_starts_with($n, "titulo")
            || str_contains($n, "nomedoitem")
            || str_contains($n, "nomedoproduto")
            || str_contains($n, "descricaodoitem");
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
