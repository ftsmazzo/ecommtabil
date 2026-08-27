<?php

namespace App\Services\Caixa;

use App\Core\DB;
use App\Models\DreConta;

/**
 * Resolve o grupo DFC (operacional / investimento / financiamento) a partir da conta ou do memo.
 */
class DfcGrupoResolver
{
    public const OPERACIONAL = "operacional";
    public const INVESTIMENTO = "investimento";
    public const FINANCIAMENTO = "financiamento";

    /** @var array<int,string> */
    private static array $cacheConta = [];

    /** @var array<string,int> */
    private static array $cacheGrupoRaiz = [];

    public static function garantirPlanoComGrupos(int $idUsuario = 0): void
    {
        DreConta::garantirPlanoDfcComGrupos($idUsuario);
    }

    public static function grupoDaConta(?int $idConta): ?string
    {
        if (!$idConta) {
            return null;
        }
        if (isset(self::$cacheConta[$idConta])) {
            return self::$cacheConta[$idConta];
        }

        $conta = DreConta::find($idConta);
        if (!$conta) {
            return null;
        }

        $grupo = self::grupoPorNome((string) ($conta->nome ?? ""));
        if ($grupo !== null) {
            self::$cacheConta[$idConta] = $grupo;
            return $grupo;
        }

        $idPai = (int) ($conta->id_pai ?? 0);
        $depth = 0;
        while ($idPai > 0 && $depth < 8) {
            $pai = DreConta::find($idPai);
            if (!$pai) {
                break;
            }
            $g = self::grupoPorNomeSintetico((string) ($pai->nome ?? ""));
            if ($g !== null) {
                self::$cacheConta[$idConta] = $g;
                return $g;
            }
            $idPai = (int) ($pai->id_pai ?? 0);
            $depth++;
        }

        return null;
    }

    public static function idContaPorNomeEGrupo(string $nomeConta, string $grupo): ?int
    {
        self::carregarIndice();
        $normNome = self::norm($nomeConta);
        $normGrupo = self::norm($grupo);
        foreach (self::$cacheGrupoRaiz as $key => $id) {
            if (str_starts_with($key, $normGrupo . "|") && str_ends_with($key, "|" . $normNome)) {
                return $id;
            }
        }
        return DreConta::buscarAnaliticaPorTexto("dfc", $nomeConta)?->id ?? null;
    }

    public static function grupoPorMemo(string $memo): ?string
    {
        $n = self::norm($memo);
        if (preg_match('/(entrada\s*ted|pix\s*receb|recebimento|cliente|venda)/', $n)) {
            return self::OPERACIONAL;
        }
        if (preg_match('/(sispag|salario|folha|fornecedor|concessionaria|tarifa|custas|imposto|tributo|darf|gps|das)/', $n)) {
            return self::OPERACIONAL;
        }
        if (preg_match('/(aplic\s*aut|res\s*aplic|capex|imobilizado|equipamento)/', $n)) {
            return self::INVESTIMENTO;
        }
        if (preg_match('/(banco\s*bv|banco\s*pan|emprestimo|financiamento|amortiz|iof|juros\s*pag)/', $n)) {
            return self::FINANCIAMENTO;
        }
        return null;
    }

    private static function grupoPorNome(string $nome): ?string
    {
        $n = self::norm($nome);
        if (str_contains($n, "imobilizado") || str_contains($n, "capex") || str_contains($n, "aplic")) {
            return self::INVESTIMENTO;
        }
        if (str_contains($n, "emprestimo") || str_contains($n, "amortiz") || str_contains($n, "juros")) {
            return self::FINANCIAMENTO;
        }
        if (
            str_contains($n, "recebimento") || str_contains($n, "fornecedor")
            || str_contains($n, "despesasoperacionais") || str_contains($n, "tributo")
            || str_contains($n, "rendimento")
        ) {
            return self::OPERACIONAL;
        }
        return null;
    }

    private static function grupoPorNomeSintetico(string $nome): ?string
    {
        $n = self::norm($nome);
        if (str_contains($n, "operacional")) {
            return self::OPERACIONAL;
        }
        if (str_contains($n, "investimento")) {
            return self::INVESTIMENTO;
        }
        if (str_contains($n, "financiamento")) {
            return self::FINANCIAMENTO;
        }
        return null;
    }

    private static function carregarIndice(): void
    {
        if (self::$cacheGrupoRaiz !== []) {
            return;
        }

        $rows = DB::table("dre_conta")
            ->whereRaw("LOWER(tipo_demonstrativo) = ?", ["dfc"])
            ->where("tipo", "=", "analitica")
            ->where("trash", "=", 0)
            ->get();

        foreach ($rows as $c) {
            $grupo = self::grupoDaConta((int) $c->id) ?? self::grupoPorNome((string) $c->nome);
            if ($grupo === null) {
                continue;
            }
            $key = self::norm($grupo) . "|" . self::norm((string) $c->nome);
            self::$cacheGrupoRaiz[$key] = (int) $c->id;
        }
    }

    private static function norm(string $t): string
    {
        $t = mb_strtolower(trim($t), "UTF-8");
        $ascii = @iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $t);
        $t = is_string($ascii) ? $ascii : $t;
        return (string) preg_replace("/[^a-z0-9]+/", "", $t);
    }
}
