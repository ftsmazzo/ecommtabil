<?php

namespace App\Models;

use App\Core\Model;
use App\Core\DB;

class DreConta extends Model
{
    public static string $table = "dre_conta";
    public static ?string $alias = "dc";
    protected static array $required = ["nome"];
    public static array $uppers = [];

    public static function applySoftDeleteScope($query)
    {
        return $query->where("dc.trash", "=", 0);
    }

    private static function resolverTipo(?string $tipo): string
    {
        if ($tipo) {
            return strtolower(trim($tipo));
        }

        $padrao = TipoDemonstrativo::padrao();
        return $padrao ? strtolower((string) $padrao->sigla) : "dre";
    }

    /**
     * Retorna a árvore de um tipo de demonstrativo como array indexado por id_pai.
     * [ id_pai => [conta, conta, ...] ]
     */
    public static function arvore(?string $tipo = null): array
    {
        $tipo = self::resolverTipo($tipo);

        $todas = DB::table("dre_conta")
            ->where("tipo_demonstrativo", "=", $tipo)
            ->where("trash", "=", 0)
            ->orderBy("ordem")
            ->orderBy("id")
            ->get();

        $map = [];
        foreach ($todas as $conta) {
            $map[$conta->id_pai ?? 0][] = $conta;
        }

        return $map;
    }

    /**
     * Retorna as contas de um tipo de demonstrativo filtradas por tipo (sintetica/analitica).
     */
    public static function porTipoConta(?string $tipo, string $tipoConta): array
    {
        $tipo = self::resolverTipo($tipo);

        return DB::table("dre_conta")
            ->where("tipo_demonstrativo", "=", $tipo)
            ->where("tipo", "=", $tipoConta)
            ->where("trash", "=", 0)
            ->orderBy("ordem")
            ->orderBy("id")
            ->get();
    }

    /**
     * Retorna todas as contas (sintéticas e analíticas) de um tipo de demonstrativo,
     * ordenadas por código. Usado onde é preciso vincular qualquer conta do plano
     * (ex: Estrutura da DRE), não só as analíticas.
     */
    public static function todas(?string $tipo = null): array
    {
        $tipo = self::resolverTipo($tipo);

        return DB::table("dre_conta")
            ->where("tipo_demonstrativo", "=", $tipo)
            ->where("trash", "=", 0)
            ->orderBy("codigo")
            ->get();
    }

    /**
     * Retorna apenas as contas analíticas de um tipo, agrupadas por categoria (nível 1).
     * Usado para popular o select de mapeamento de colunas.
     */
    public static function analiticasPorTipo(?string $tipo): array
    {
        $tipo = self::resolverTipo($tipo);

        $contas = DB::table("dre_conta")
            ->whereRaw("LOWER(tipo_demonstrativo) = ?", [$tipo])
            ->where("tipo", "=", "analitica")
            ->where("trash", "=", 0)
            ->orderBy("codigo")
            ->get();

        // Agrupa pelo código de nível 1 (primeiro segmento do código)
        $grupos = [];
        foreach ($contas as $conta) {
            $raiz = explode(".", $conta->codigo)[0];
            $grupos[$raiz][] = $conta;
        }

        return $grupos;
    }

    /**
     * Lista plana das analíticas (sem agrupamento).
     *
     * @return array<int,object>
     */
    public static function analiticasLista(?string $tipo): array
    {
        $lista = [];
        foreach (self::analiticasPorTipo($tipo) as $contas) {
            foreach ($contas as $conta) {
                $lista[] = $conta;
            }
        }
        return $lista;
    }

    /**
     * Resolve uma conta analítica pelo texto da planilha (código, nome ou "código nome").
     * Ignora sintéticas e linhas de resultado calculado.
     */
    public static function buscarAnaliticaPorTexto(?string $tipo, string $texto): ?object
    {
        $tipo  = self::resolverTipo($tipo);
        $texto = trim($texto);
        if ($texto === "") {
            return null;
        }

        static $cache = [];
        if (!isset($cache[$tipo])) {
            $cache[$tipo] = DB::table("dre_conta")
                ->whereRaw("LOWER(tipo_demonstrativo) = ?", [$tipo])
                ->where("tipo", "=", "analitica")
                ->where("trash", "=", 0)
                ->where("eh_resultado", "=", 0)
                ->get();
        }

        $norm = static function (string $t): string {
            $t = mb_strtolower(trim($t), "UTF-8");
            $ascii = @iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $t);
            $t = is_string($ascii) ? $ascii : $t;
            return (string) preg_replace("/[^a-z0-9]+/", "", $t);
        };

        $nTexto  = $norm($texto);
        return self::casarEmLista($cache[$tipo], $texto, $norm);
    }

    /**
     * Casa um texto da planilha com uma conta da lista. Só devolve se o match for único.
     *
     * @param array<int,object> $contas
     * @param callable|null $norm
     */
    public static function casarEmLista(array $contas, string $texto, ?callable $norm = null): ?object
    {
        $texto = trim($texto);
        if ($texto === "" || $contas === []) {
            return null;
        }

        $norm = $norm ?? static function (string $t): string {
            $t = mb_strtolower(trim($t), "UTF-8");
            $ascii = @iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $t);
            $t = is_string($ascii) ? $ascii : $t;
            return (string) preg_replace("/[^a-z0-9]+/", "", $t);
        };

        $nTexto = $norm($texto);
        if ($nTexto === "") {
            return null;
        }

        foreach ($contas as $conta) {
            $codigo = trim((string) ($conta->codigo ?? ""));
            $nome   = trim((string) ($conta->nome ?? ""));
            if (strcasecmp($codigo, $texto) === 0 || strcasecmp($nome, $texto) === 0) {
                return $conta;
            }
            if ($codigo !== "" && preg_match('/^' . preg_quote($codigo, "/") . '(?:\s+.+)?$/u', $texto)) {
                return $conta;
            }
        }

        $iguais = [];
        foreach ($contas as $conta) {
            if ($norm((string) ($conta->nome ?? "")) === $nTexto || $norm((string) ($conta->codigo ?? "")) === $nTexto) {
                $iguais[] = $conta;
            }
        }
        if (count($iguais) === 1) {
            return $iguais[0];
        }

        // "Receita Bruta de Vendas" → conta "Receita Bruta" (match único pelo nome mais longo)
        $parciais = [];
        $melhorLen = 0;
        foreach ($contas as $conta) {
            $nn = $norm((string) ($conta->nome ?? ""));
            if (strlen($nn) < 6) {
                continue;
            }
            if ($nn === $nTexto || str_starts_with($nTexto, $nn) || str_contains($nTexto, $nn)) {
                $len = strlen($nn);
                if ($len > $melhorLen) {
                    $parciais = [$conta];
                    $melhorLen = $len;
                } elseif ($len === $melhorLen) {
                    $parciais[] = $conta;
                }
            }
        }
        if (count($parciais) === 1) {
            return $parciais[0];
        }

        return null;
    }

    /**
     * Garante as contas analíticas mínimas do SAGA (vendas ML/Shopee, despesas, DRE).
     * Não apaga TESTE A nem contas já existentes — só cria o que faltar.
     *
     * @return int quantidade criada
     */
    public static function garantirPlanoSagaPadrao(string $tipo, int $idUsuario = 0): int
    {
        $tipo = self::resolverTipo($tipo);
        $criadas = 0;

        $catalogo = [
            "dre" => [
                ["Receita Bruta", "aumenta"],
                ["Receita por envio", "aumenta"],
                ["Deduções", "diminui"],
                ["Tarifa de venda e impostos", "diminui"],
                ["Cupons e Descontos", "diminui"],
                ["CMV", "diminui"],
                ["Despesas Operacionais", "diminui"],
                ["Folha administrativa", "diminui"],
                ["Aluguel", "diminui"],
                ["Marketing", "diminui"],
                ["Tarifas de Envio", "diminui"],
                ["Comissão de Afiliados", "diminui"],
                ["Frete Entrega Direta", "diminui"],
                ["Depreciação", "diminui"],
                ["Resultado Financeiro", "diminui"],
                ["Receita Bruta de Vendas", "aumenta"],
                ["Devoluções e Cancelamentos", "diminui"],
                ["CMV (Custo das Mercadorias Vendidas)", "diminui"],
                ["Comissões de Marketplace", "diminui"],
                ["Frete Líquido (Subsídio de Frete)", "diminui"],
                ["Depreciação e Amortização", "diminui"],
                ["Despesas Financeiras", "diminui"],
                ["Receitas Financeiras", "aumenta"],
                ["Provisão para IR/CSLL", "diminui"],
            ],
            "dfc" => [
                ["Recebimentos de Clientes", "aumenta"],
                ["Rendimentos Financeiros Recebidos", "aumenta"],
                ["Pagamentos a Fornecedores", "diminui"],
                ["Pagamento de Despesas Operacionais", "diminui"],
                ["Pagamento de Tributos", "diminui"],
                ["Aquisição de Imobilizado (Capex)", "diminui"],
                ["Captação de Empréstimos", "aumenta"],
                ["Amortização de Empréstimos", "diminui"],
                ["Juros Pagos", "diminui"],
                ["Saldo Inicial de Caixa", "aumenta"],
                ["Saldo Final de Caixa", "aumenta"],
            ],
            "bp" => [
                ["Caixa e Equivalentes de Caixa", "aumenta"],
                ["Caixa e Equivalentes", "aumenta"],
                ["Contas a Receber (Clientes)", "aumenta"],
                ["Contas a Receber", "aumenta"],
                ["Estoques", "aumenta"],
                ["Impostos a Recuperar", "aumenta"],
                ["Imobilizado (Bruto)", "aumenta"],
                ["Depreciação Acumulada", "diminui"],
                ["Fornecedores a Pagar", "diminui"],
                ["Fornecedores", "diminui"],
                ["Empréstimos e Financiamentos (Circulante)", "diminui"],
                ["Empréstimos CP", "diminui"],
                ["Impostos a Recolher", "diminui"],
                ["Salários e Encargos a Pagar", "diminui"],
                ["Empréstimos e Financiamentos (Não Circulante)", "diminui"],
                ["Empréstimos LP", "diminui"],
                ["Capital Social", "aumenta"],
                ["Lucros Acumulados", "aumenta"],
                ["Patrimônio Líquido", "aumenta"],
            ],
        ];

        $lista = $catalogo[$tipo] ?? $catalogo["dre"];

        $existentes = DB::table("dre_conta")
            ->whereRaw("LOWER(tipo_demonstrativo) = ?", [$tipo])
            ->where("trash", "=", 0)
            ->get();

        $norm = static function (string $t): string {
            $t = mb_strtolower(trim($t), "UTF-8");
            $ascii = @iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $t);
            $t = is_string($ascii) ? $ascii : $t;
            return (string) preg_replace("/[^a-z0-9]+/", "", $t);
        };

        $jaTem = [];
        foreach ($existentes as $row) {
            $jaTem[$norm((string) $row->nome)] = true;
        }

        foreach ($lista as $ordem => [$nome, $natureza]) {
            if (isset($jaTem[$norm($nome)])) {
                continue;
            }
            $codigo = self::gerarCodigo(null, $tipo);
            self::create([
                "tipo_demonstrativo" => $tipo,
                "id_pai"             => null,
                "nivel"              => 1,
                "codigo"             => $codigo,
                "nome"               => $nome,
                "tipo"               => "analitica",
                "natureza"           => $natureza,
                "sinal"              => $natureza === "diminui" ? -1 : 1,
                "eh_resultado"       => 0,
                "ordem"              => 100 + $ordem,
                "trash"              => 0,
                "created_by"         => $idUsuario ?: null,
            ]);
            $jaTem[$norm($nome)] = true;
            $criadas++;
        }

        return $criadas;
    }

    /**
     * Plano DFC com 3 grupos (Operacional / Investimento / Financiamento) e contas analíticas filhas.
     */
    public static function garantirPlanoDfcComGrupos(int $idUsuario = 0): int
    {
        self::garantirPlanoSagaPadrao("dfc", $idUsuario);

        $criadas = 0;
        $norm = static function (string $t): string {
            $t = mb_strtolower(trim($t), "UTF-8");
            $ascii = @iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $t);
            $t = is_string($ascii) ? $ascii : $t;
            return (string) preg_replace("/[^a-z0-9]+/", "", $t);
        };

        $estrutura = [
            "Atividades Operacionais" => [
                ["Recebimentos de Clientes", "aumenta"],
                ["Pagamentos a Fornecedores", "diminui"],
                ["Pagamento de Despesas Operacionais", "diminui"],
                ["Pagamento de Tributos", "diminui"],
                ["Rendimentos Financeiros Recebidos", "aumenta"],
            ],
            "Atividades de Investimento" => [
                ["Aquisição de Imobilizado (Capex)", "diminui"],
            ],
            "Atividades de Financiamento" => [
                ["Captação de Empréstimos", "aumenta"],
                ["Amortização de Empréstimos", "diminui"],
                ["Juros Pagos", "diminui"],
            ],
        ];

        foreach ($estrutura as $grupoNome => $filhas) {
            $idPai = self::buscarOuCriarSintetica("dfc", $grupoNome, $idUsuario, $criadas, $norm);
            foreach ($filhas as [$nome, $natureza]) {
                if (self::buscarOuCriarAnaliticaFilha("dfc", $idPai, $nome, $natureza, $idUsuario, $norm)) {
                    $criadas++;
                }
            }
        }

        return $criadas;
    }

    private static function buscarOuCriarSintetica(
        string $tipo,
        string $nome,
        int $idUsuario,
        int &$criadas,
        callable $norm
    ): int {
        $rows = DB::table("dre_conta")
            ->whereRaw("LOWER(tipo_demonstrativo) = ?", [$tipo])
            ->where("trash", "=", 0)
            ->where("tipo", "=", "sintetica")
            ->get();

        foreach ($rows as $r) {
            if ($norm((string) $r->nome) === $norm($nome)) {
                return (int) $r->id;
            }
        }

        $codigo = self::gerarCodigo(null, $tipo);
        $ins = self::create([
            "tipo_demonstrativo" => $tipo,
            "id_pai"             => null,
            "nivel"              => 1,
            "codigo"             => $codigo,
            "nome"               => $nome,
            "tipo"               => "sintetica",
            "natureza"           => "aumenta",
            "sinal"              => 1,
            "eh_resultado"       => 0,
            "ordem"              => 10,
            "trash"              => 0,
            "created_by"         => $idUsuario ?: null,
        ]);
        $criadas++;
        return (int) ($ins->id ?? 0);
    }

    private static function buscarOuCriarAnaliticaFilha(
        string $tipo,
        int $idPai,
        string $nome,
        string $natureza,
        int $idUsuario,
        callable $norm
    ): bool {
        $existentes = DB::table("dre_conta")
            ->whereRaw("LOWER(tipo_demonstrativo) = ?", [$tipo])
            ->where("trash", "=", 0)
            ->where("tipo", "=", "analitica")
            ->get();

        foreach ($existentes as $r) {
            if ($norm((string) $r->nome) === $norm($nome)) {
                if ((int) ($r->id_pai ?? 0) !== $idPai) {
                    DB::table("dre_conta")->where("id", "=", (int) $r->id)->update(["id_pai" => $idPai]);
                }
                return false;
            }
        }

        $codigo = self::gerarCodigo($idPai, $tipo);
        self::create([
            "tipo_demonstrativo" => $tipo,
            "id_pai"             => $idPai,
            "nivel"              => 2,
            "codigo"             => $codigo,
            "nome"               => $nome,
            "tipo"               => "analitica",
            "natureza"           => $natureza,
            "sinal"              => $natureza === "diminui" ? -1 : 1,
            "eh_resultado"       => 0,
            "ordem"              => 10,
            "trash"              => 0,
            "created_by"         => $idUsuario ?: null,
        ]);
        return true;
    }

    /**
     * Gera o código da conta a partir do pai, dentro de um tipo de demonstrativo.
     * Ex: pai com código "1.2" e 3 filhos existentes → "1.2.4"
     */
    public static function gerarCodigo(?int $idPai, ?string $tipo = null): string
    {
        $tipo = self::resolverTipo($tipo);

        if (!$idPai) {
            $total = DB::table("dre_conta")
                ->whereNull("id_pai")
                ->where("tipo_demonstrativo", "=", $tipo)
                ->where("trash", "=", 0)
                ->count();
            return (string) ($total + 1);
        }

        $pai = DB::table("dre_conta")->where("id", "=", $idPai)->first();
        if (!$pai) return "0";

        $total = DB::table("dre_conta")
            ->where("id_pai", "=", $idPai)
            ->where("trash", "=", 0)
            ->count();

        return $pai->codigo . "." . ($total + 1);
    }

    /**
     * Recalcula e salva a ordem de uma lista de ids.
     */
    public static function reordenar(array $ids): void
    {
        foreach ($ids as $ordem => $id) {
            DB::table("dre_conta")
                ->where("id", "=", (int) $id)
                ->update(["ordem" => (int) $ordem]);
        }
    }
}
