<?php

namespace App\Models;

use App\Core\Model;

class ProjetoLancamento extends Model
{
    public static string $table = "projeto_lancamento";
    public static ?string $alias = "pl";
    protected static array $required = ["id_projeto", "linha", "mapeamento"];
    public static array $uppers = [];
    public static array $nulls = ["id_dre_conta", "periodo", "descricao", "valor", "unidade"];

    public static function applySoftDeleteScope($query)
    {
        return $query->where("pl.trash", "=", 0);
    }

    public static function porProjeto(int $idProjeto, ?string $tipo = null): array
    {
        $q = self::query()->where("pl.id_projeto", "=", $idProjeto);
        if ($tipo) {
            $q->where("pl.tipo_demonstrativo", "=", $tipo);
        }
        return $q->orderBy("pl.periodo")->orderBy("pl.id_dre_conta")->get();
    }

    public static function resumoPorProjeto(int $idProjeto, ?string $tipo = null): object
    {
        $result = \App\Core\DB::execute(
            "SELECT
                COUNT(*) AS total_lancamentos,
                COUNT(DISTINCT periodo) AS total_periodos,
                COUNT(DISTINCT id_dre_conta) AS total_contas,
                COALESCE(SUM(valor), 0) AS valor_total
            FROM projeto_lancamento
            WHERE id_projeto = ? AND trash = 0" . ($tipo ? " AND LOWER(tipo_demonstrativo) = LOWER(?)" : ""),
            $tipo ? [$idProjeto, $tipo] : [$idProjeto]
        );
        $row = $result[0] ?? [];
        return (object) [
            "total_lancamentos" => (int) ($row->total_lancamentos ?? $row["total_lancamentos"] ?? 0),
            "total_periodos"    => (int) ($row->total_periodos ?? $row["total_periodos"] ?? 0),
            "total_contas"      => (int) ($row->total_contas ?? $row["total_contas"] ?? 0),
            "valor_total"       => (float) ($row->valor_total ?? $row["valor_total"] ?? 0),
        ];
    }

    public static function totaisGerais(): object
    {
        $result = \App\Core\DB::execute(
            "SELECT
                COUNT(*) AS total_lancamentos,
                COUNT(DISTINCT id_projeto) AS projetos_com_dado,
                COALESCE(SUM(valor), 0) AS valor_total
             FROM projeto_lancamento
             WHERE trash = 0"
        );
        $row = $result[0] ?? [];
        return (object) [
            "total_lancamentos" => (int) ($row->total_lancamentos ?? $row["total_lancamentos"] ?? 0),
            "projetos_com_dado" => (int) ($row->projetos_com_dado ?? $row["projetos_com_dado"] ?? 0),
            "valor_total"       => (float) ($row->valor_total ?? $row["valor_total"] ?? 0),
        ];
    }

    public static function limparPorProjeto(int $idProjeto, string $tipo, int $aba): void
    {
        \App\Core\DB::table(static::$table)
            ->where("id_projeto", "=", $idProjeto)
            ->where("tipo_demonstrativo", "=", $tipo)
            ->where("aba", "=", $aba)
            ->update(["trash" => 1]);
    }
}
