<?php

namespace App\Models;

use App\Core\Model;

class FidelidadeRegra extends Model
{
    public static string $table = "fidelidade_regra";
    public static ?string $alias = "fr";

    protected static array $required = [
        "descricao",
        "valor_saldo",
    ];

    protected static array $uppers = [
        "descricao",
        "observacoes",
    ];

    public static function applySoftDeleteScope($query)
    {
        return $query->where("fr.trash", "=", 0);
    }
}
