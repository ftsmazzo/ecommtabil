<?php

namespace App\Models;

use App\Core\Model;

class CaixaSessao extends Model
{
    public static string $table = "caixa_sessao";
    public static ?string $alias = "cs";

    public static function applySoftDeleteScope($query)
    {
        return $query->where("cs.trash", "=", 0);
    }

    public static function ativasPorProjeto(int $idProjeto): array
    {
        return self::where("cs.id_projeto", "=", $idProjeto)
            ->orderBy("cs.id", "DESC")
            ->get();
    }

    public static function findAtiva(int $id, int $idProjeto): ?self
    {
        $row = self::where("cs.id", "=", $id)
            ->where("cs.id_projeto", "=", $idProjeto)
            ->first();
        return $row ?: null;
    }
}
