<?php

namespace App\Services\Bp;

use App\Core\DB;

/**
 * Valida a equação contábil do BP: Ativo = Passivo + Patrimônio Líquido.
 * O sistema recebe valores já apurados; não recalcula o balanço.
 */
class BpEquilibrioService
{
    public const GRUPO_ATIVO             = "ativo";
    public const GRUPO_PASSIVO           = "passivo";
    public const GRUPO_PL                = "pl";
    public const GRUPO_TOTAL_ATIVO       = "total_ativo";
    public const GRUPO_TOTAL_PASSIVO_PL  = "total_passivo_pl";
    public const GRUPO_IGNORADO          = "ignorado";

    private const TOLERANCIA = 0.02;

    /**
     * @return array{
     *   tem_dados:bool,
     *   periodos:array<string,array<string,mixed>>,
     *   ultimo:?array<string,mixed>,
     *   mensagem_resumo:?string
     * }
     */
    public function conferirProjeto(int $idProjeto, ?string $periodo = null): array
    {
        $sql = "SELECT pl.periodo, pl.valor, dc.nome AS conta_nome, dc.natureza
                FROM projeto_lancamento pl
                LEFT JOIN dre_conta dc ON dc.id = pl.id_dre_conta
                WHERE pl.id_projeto = ?
                  AND pl.trash = 0
                  AND LOWER(pl.tipo_demonstrativo) = 'bp'";
        $params = [$idProjeto];
        if ($periodo !== null && $periodo !== "") {
            $sql .= " AND pl.periodo = ?";
            $params[] = $periodo;
        }
        $sql .= " ORDER BY pl.periodo, pl.id";

        $rows = DB::execute($sql, $params);
        $porPeriodo = [];
        foreach ($rows as $row) {
            $p = (string) ($row->periodo ?? $row["periodo"] ?? "");
            if ($p === "") {
                $p = "_sem_periodo";
            }
            $porPeriodo[$p][] = [
                "conta"    => (string) ($row->conta_nome ?? $row["conta_nome"] ?? ""),
                "valor"    => (float) ($row->valor ?? $row["valor"] ?? 0),
                "natureza" => (string) ($row->natureza ?? $row["natureza"] ?? ""),
            ];
        }

        return $this->montarResultado($porPeriodo);
    }

    /**
     * Conferência a partir de amostras da simulação (sem gravar).
     *
     * @param array<int,array{conta?:string,valor?:float|int,periodo?:?string}> $amostras
     * @return array<string,mixed>
     */
    public function conferirAmostras(array $amostras): array
    {
        $porPeriodo = [];
        foreach ($amostras as $a) {
            $p = trim((string) ($a["periodo"] ?? ""));
            if ($p === "") {
                $p = "_sem_periodo";
            }
            $porPeriodo[$p][] = [
                "conta"    => (string) ($a["conta"] ?? ""),
                "valor"    => (float) ($a["valor"] ?? 0),
                "natureza" => (string) ($a["natureza"] ?? ""),
            ];
        }

        return $this->montarResultado($porPeriodo);
    }

    /**
     * @param array<string,array<int,array{conta:string,valor:float,natureza:string}>> $porPeriodo
     * @return array{tem_dados:bool,periodos:array<string,array<string,mixed>>,ultimo:?array<string,mixed>,mensagem_resumo:?string}
     */
    private function montarResultado(array $porPeriodo): array
    {
        if ($porPeriodo === []) {
            return [
                "tem_dados"       => false,
                "periodos"        => [],
                "ultimo"          => null,
                "mensagem_resumo" => null,
            ];
        }

        $periodos = [];
        foreach ($porPeriodo as $periodo => $linhas) {
            $periodos[$periodo] = $this->conferirLinhas($linhas, $periodo);
        }

        // Exercício atual = maior data (não a ordem de inserção das amostras)
        $chaves = array_keys($periodos);
        usort($chaves, static function (string $a, string $b): int {
            if ($a === "_sem_periodo") {
                return -1;
            }
            if ($b === "_sem_periodo") {
                return 1;
            }
            return strcmp($a, $b);
        });
        $ordenado = [];
        foreach ($chaves as $k) {
            $ordenado[$k] = $periodos[$k];
        }
        $periodos = $ordenado;
        $ultimo = $periodos !== [] ? $periodos[array_key_last($periodos)] : null;
        $mensagem = $this->mensagemResumo($ultimo);

        return [
            "tem_dados"       => true,
            "periodos"        => $periodos,
            "ultimo"          => $ultimo,
            "mensagem_resumo" => $mensagem,
        ];
    }

    /**
     * @param array<int,array{conta:string,valor:float,natureza:string}> $linhas
     * @return array<string,mixed>
     */
    private function conferirLinhas(array $linhas, string $periodo): array
    {
        $ativo = 0.0;
        $passivo = 0.0;
        $pl = 0.0;
        $totalAtivoDeclarado = null;
        $totalPassivoPlDeclarado = null;
        $naoClassificadas = [];
        $detalhes = [];

        foreach ($linhas as $linha) {
            $nome = trim($linha["conta"]);
            if ($nome === "") {
                continue;
            }

            $grupo = $this->classificarConta($nome, $linha["natureza"] ?: null);
            $valor = (float) $linha["valor"];
            $magnitude = abs($valor);

            if ($grupo === self::GRUPO_TOTAL_ATIVO) {
                $totalAtivoDeclarado = $magnitude;
                continue;
            }
            if ($grupo === self::GRUPO_TOTAL_PASSIVO_PL) {
                $totalPassivoPlDeclarado = $magnitude;
                continue;
            }
            if ($grupo === self::GRUPO_IGNORADO) {
                continue;
            }

            if ($grupo === self::GRUPO_ATIVO) {
                $ativo += $this->valorAtivo($valor, $nome);
            } elseif ($grupo === self::GRUPO_PASSIVO) {
                $passivo += abs($valor);
            } elseif ($grupo === self::GRUPO_PL) {
                $pl += $this->valorPl($valor, $nome);
            } else {
                $naoClassificadas[] = $nome;
                continue;
            }

            $detalhes[] = [
                "conta"  => $nome,
                "grupo"  => $grupo,
                "valor"  => $valor,
                "usado"  => $grupo === self::GRUPO_ATIVO
                    ? $this->valorAtivo($valor, $nome)
                    : ($grupo === self::GRUPO_PL ? $this->valorPl($valor, $nome) : ($valor < 0 ? abs($valor) : abs($valor))),
            ];
        }

        $usaTotais = $totalAtivoDeclarado !== null && $totalPassivoPlDeclarado !== null;
        $ativoFinal = $usaTotais ? $totalAtivoDeclarado : $ativo;
        $passivoMaisPl = $usaTotais ? $totalPassivoPlDeclarado : ($passivo + $pl);
        $diferenca = round($ativoFinal - $passivoMaisPl, 2);
        $fecha = abs($diferenca) <= self::TOLERANCIA;

        return [
            "periodo"                    => $periodo,
            "periodo_label"              => $this->rotuloPeriodo($periodo),
            "ativo"                      => round($ativoFinal, 2),
            "passivo"                    => round($passivo, 2),
            "pl"                         => round($pl, 2),
            "passivo_mais_pl"            => round($passivoMaisPl, 2),
            "diferenca"                  => $diferenca,
            "fecha"                      => $fecha,
            "usa_totais_declarados"      => $usaTotais,
            "total_ativo_declarado"      => $totalAtivoDeclarado,
            "total_passivo_pl_declarado" => $totalPassivoPlDeclarado,
            "nao_classificadas"          => array_values(array_unique($naoClassificadas)),
            "detalhes"                   => $detalhes,
            "incompleto"                 => !$usaTotais && $passivo <= 0 && $pl >= 0 && $ativo > 0,
        ];
    }

    private function valorPl(float $valor, string $nome): float
    {
        $n = $this->normalizar($nome);
        if ($this->contemAlgum($n, ["prejuizo", "prejuizosacumulados"]) || str_contains($nome, "(-)")) {
            return $valor < 0 ? $valor : -abs($valor);
        }

        return $valor;
    }

    public function classificarConta(string $nome, ?string $natureza = null): string
    {
        $n = $this->normalizar($nome);

        if ($this->contemAlgum($n, [
            "totaldoativo", "totalativo", "somadoativo", "totaldosativos",
        ])) {
            return self::GRUPO_TOTAL_ATIVO;
        }

        if ($this->contemAlgum($n, [
            "totaldopassivo", "totalpassivo", "passivoepatrimonio", "passivomaispl",
            "totaldopassivoepatrimonioliquido", "totalpassivoepatrimonioliquido",
            "totaldopassivoepl", "totalpassivoepl",
        ])) {
            return self::GRUPO_TOTAL_PASSIVO_PL;
        }

        if ($this->contemAlgum($n, ["passivoadescoberto", "descoberto"])) {
            return self::GRUPO_IGNORADO;
        }

        if ($this->contemAlgum($n, [
            "subtotal", "totalgeral", "resultado", "demonstrativo",
        ]) && !$this->contemAlgum($n, ["ativo", "passivo", "patrimonio", "liquido"])) {
            return self::GRUPO_IGNORADO;
        }

        if ($this->contemAlgum($n, [
            "capitalsocial", "reservas", "reservadecapital", "reservadelucros",
            "lucrosacumulados", "lucroacumulado", "prejuizoacumulado", "prejuizosacumulados",
            "patrimonioliquido", "patrimonioliquidoajustado", "ajustedeavaliacao",
            "subvencao", "acoesemtesouraria", "resultadoexercicio",
        ])) {
            return self::GRUPO_PL;
        }

        if ($this->contemAlgum($n, [
            "fornecedor", "contasapagar", "apagar", "emprestimo", "financiamento",
            "impostoarecolher", "impostosarecolher", "salario", "salarios",
            "encargos", "obrigacao", "passivo", "provisao", "tributosapagar",
            "adiantamentodecliente", "divida", "debenture", "parcelamento",
            "cofins", "icms", "pis", "tributosfederais", "recolher",
        ]) && !$this->contemAlgum($n, ["recuperar", "receber"])) {
            return self::GRUPO_PASSIVO;
        }

        if ($this->contemAlgum($n, [
            "caixa", "banco", "equivalente", "receber", "estoque", "impostorecuperar",
            "impostosrecuperar", "imobilizado", "veiculo", "maquina", "terreno",
            "servidor", "camera", "aplicacao", "investimento", "cliente", "direito",
            "ativo", "credito", "adiantamentoafornecedor", "deposito", "cobranca",
            "depreciacaoacumulada", "amortizacaoacumulada",
        ])) {
            return self::GRUPO_ATIVO;
        }

        if ($natureza === "diminui") {
            return self::GRUPO_PASSIVO;
        }
        if ($natureza === "aumenta") {
            return self::GRUPO_ATIVO;
        }

        return "desconhecido";
    }

    /**
     * @param ?array<string,mixed> $ultimo
     */
    public function mensagemResumo(?array $ultimo): ?string
    {
        if (!$ultimo) {
            return null;
        }

        $periodo = (string) ($ultimo["periodo_label"] ?? "");
        $prefixo = $periodo !== "" ? "Período {$periodo}: " : "";

        if (!empty($ultimo["incompleto"])) {
            return $prefixo . "Ativo informado, mas Passivo e/ou PL ausentes — a equação não pode ser validada.";
        }

        if (!empty($ultimo["nao_classificadas"])) {
            $n = count($ultimo["nao_classificadas"]);
            $extra = $n > 3 ? " (+". ($n - 3) .")" : "";
            $lista = implode(", ", array_slice($ultimo["nao_classificadas"], 0, 3));
            return $prefixo . "Contas não classificadas ({$lista}{$extra}) — revise o plano ou os nomes importados.";
        }

        if (!empty($ultimo["fecha"])) {
            return $prefixo . "Equação contábil OK: Ativo = Passivo + PL (R$ "
                . $this->formatar((float) $ultimo["ativo"]) . ").";
        }

        $dif = $this->formatar(abs((float) ($ultimo["diferenca"] ?? 0)));
        return $prefixo . "Equação não fecha: diferença de R$ {$dif}. Revise a apuração do contador.";
    }

    private function valorAtivo(float $valor, string $nome): float
    {
        $n = $this->normalizar($nome);
        if ($this->contemAlgum($n, ["depreciacaoacumulada", "amortizacaoacumulada"])) {
            return -abs($valor);
        }

        return abs($valor);
    }

    private function rotuloPeriodo(string $periodo): string
    {
        if ($periodo === "_sem_periodo") {
            return "Sem data";
        }
        if (preg_match("/^(\d{4})-(\d{2})-\d{2}$/", $periodo, $m)) {
            return $m[2] . "/" . $m[1];
        }
        if (preg_match("/^(\d{2})\/(\d{4})$/", $periodo, $m)) {
            return $m[1] . "/" . $m[2];
        }

        return $periodo;
    }

    private function formatar(float $v): string
    {
        return number_format($v, 2, ",", ".");
    }

    private function normalizar(string $texto): string
    {
        $t = mb_strtolower(trim($texto), "UTF-8");
        $ascii = @iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $t);

        return (string) preg_replace("/[^a-z0-9]+/", "", is_string($ascii) ? $ascii : $t);
    }

    /**
     * @param array<int,string> $termos
     */
    private function contemAlgum(string $normalizado, array $termos): bool
    {
        foreach ($termos as $termo) {
            if ($termo !== "" && str_contains($normalizado, $termo)) {
                return true;
            }
        }

        return false;
    }
}
