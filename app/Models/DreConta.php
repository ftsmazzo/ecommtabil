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
            return $tipo;
        }

        $padrao = TipoDemonstrativo::padrao();
        return $padrao ? $padrao->sigla : "dre";
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
            ->where("tipo_demonstrativo", "=", $tipo)
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
