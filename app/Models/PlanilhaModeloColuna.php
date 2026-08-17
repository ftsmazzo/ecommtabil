<?php

namespace App\Models;

use App\Core\Model;

class PlanilhaModeloColuna extends Model
{
    public static string $table = "planilha_modelo_coluna";
    public static ?string $alias = "pmc";
    protected static array $required = ["descricao", "id_modelo_demonstrativo"];
    public static array $uppers = [];

    public static function applySoftDeleteScope($query)
    {
        return $query->where("pmc.trash", "=", 0);
    }

    public static function porModelo(int $idModeloDemonstrativo): array
    {
        return self::query()
            ->where("pmc.id_modelo_demonstrativo", "=", $idModeloDemonstrativo)
            ->orderBy("pmc.ordem")
            ->orderBy("pmc.id")
            ->get();
    }
}
