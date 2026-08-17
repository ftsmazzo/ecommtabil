<?php

namespace App\Models;

use App\Core\Model;
use App\Core\DB;

class ModeloDemonstrativoNo extends Model
{
    public static string $table = "modelo_demonstrativo_no";
    public static ?string $alias = "no";
    protected static array $required = ["tipo", "nome"];
    public static array $uppers = [];

    public const TIPOS = ["organizador", "conta", "totalizador"];

    public static function applySoftDeleteScope($query)
    {
        return $query->where("no.trash", "=", 0);
    }

    /**
     * Retorna a árvore de um modelo como array indexado por id_pai, já com
     * a conta vinculada em cada nó tipo "conta" (no máximo 1 por nó).
     * [ id_pai => [no, no, ...] ], cada $no ganha a propriedade "conta"
     * (dre_conta ou null). O tipo de demonstrativo já é inerente ao modelo,
     * não precisa mais ser informado aqui.
     */
    public static function arvorePorConfiguracao(int $idModelo): array
    {
        $nos = DB::table("modelo_demonstrativo_no")
            ->where("id_configuracao", "=", $idModelo)
            ->where("trash", "=", 0)
            ->orderBy("ordem")
            ->orderBy("id")
            ->get();

        if (empty($nos)) {
            return [];
        }

        $idsNo = array_map(fn ($no) => $no->id, $nos);

        $vinculos = DB::table("modelo_demonstrativo_no_conta", "noc")
            ->join("dre_conta AS c", "c.id", "=", "noc.id_conta")
            ->whereIn("noc.id_no", $idsNo)
            ->orderBy("c.codigo")
            ->select(["noc.id_no", "c.id", "c.codigo", "c.nome", "c.natureza", "c.tipo"])
            ->get();

        $contaPorNo = [];
        foreach ($vinculos as $v) {
            // No máximo 1 conta por nó — se houver mais de uma linha (dado legado), usa a primeira.
            if (isset($contaPorNo[$v->id_no])) {
                continue;
            }
            $contaPorNo[$v->id_no] = (object) [
                "id"       => $v->id,
                "codigo"   => $v->codigo,
                "nome"     => $v->nome,
                "natureza" => $v->natureza,
                "tipo"     => $v->tipo,
            ];
        }

        $map = [];
        foreach ($nos as $no) {
            $no->conta = $contaPorNo[$no->id] ?? null;
            $map[$no->id_pai ?? 0][] = $no;
        }

        return $map;
    }
}
