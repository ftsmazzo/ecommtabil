<?php

namespace App\Models;

use App\Core\Model;

class CaixaRecibo extends Model
{
    public static string $table = "caixa_recibo";
    public static ?string $alias = "cr";

    public static function applySoftDeleteScope($query)
    {
        return $query->where("cr.trash", "=", 0);
    }

    public static function porSessao(int $idSessao): array
    {
        return self::where("cr.id_sessao", "=", $idSessao)
            ->orderBy("cr.id", "DESC")
            ->get();
    }
}
