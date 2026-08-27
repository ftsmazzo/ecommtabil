<?php

namespace App\Models;

use App\Core\Model;

class CaixaMovimento extends Model
{
    public static string $table = "caixa_movimento";
    public static ?string $alias = "cm";

    public static function applySoftDeleteScope($query)
    {
        return $query->where("cm.trash", "=", 0);
    }

    public static function porSessao(int $idSessao, ?string $status = null): array
    {
        $q = self::where("cm.id_sessao", "=", $idSessao);
        if ($status !== null && $status !== "") {
            $q->where("cm.status", "=", $status);
        }
        return $q->orderBy("cm.data_posted")->orderBy("cm.id")->get();
    }

    public static function resumoPorSessao(int $idSessao): object
    {
        $rows = \App\Core\DB::execute(
            "SELECT
                status,
                COUNT(*) AS qtd,
                COALESCE(SUM(valor), 0) AS soma
             FROM caixa_movimento
             WHERE id_sessao = ? AND trash = 0
             GROUP BY status",
            [$idSessao]
        );

        $out = [
            "total"     => 0,
            "novo"      => 0,
            "sugerido"  => 0,
            "aprovado"  => 0,
            "editado"   => 0,
            "ignorado"  => 0,
            "creditos"  => 0.0,
            "debitos"   => 0.0,
        ];

        foreach ($rows as $row) {
            $st = (string) ($row->status ?? "");
            $q  = (int) ($row->qtd ?? 0);
            $out["total"] += $q;
            if (isset($out[$st])) {
                $out[$st] = $q;
            }
        }

        $totais = \App\Core\DB::execute(
            "SELECT
                COALESCE(SUM(CASE WHEN valor > 0 THEN valor ELSE 0 END), 0) AS creditos,
                COALESCE(SUM(CASE WHEN valor < 0 THEN valor ELSE 0 END), 0) AS debitos
             FROM caixa_movimento
             WHERE id_sessao = ? AND trash = 0",
            [$idSessao]
        );
        $t = $totais[0] ?? null;
        if ($t) {
            $out["creditos"] = (float) ($t->creditos ?? 0);
            $out["debitos"]  = (float) ($t->debitos ?? 0);
        }

        return (object) $out;
    }
}
