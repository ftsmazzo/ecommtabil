<?php

namespace App\Models;

use App\Core\Model;

class CashbackRegra extends Model
{
    public static string $table = "cashback_regra";
    public static ?string $alias = "cr";

    protected static array $required = [
        "descricao",
        "tipo",
        "valor",
    ];

    public static array $uppers = [
        "descricao",
        "tipo",
        "observacoes",
    ];

    public static function applySoftDeleteScope($query)
    {
        return $query->where("cr.trash", "=", 0);
    }
}
