<?php

namespace App\Models;

use App\Core\Model;

class Cartao extends Model
{
    public static string $table = "cartao";
    public static ?string $alias = "ct";

    protected static array $required = [
        "codigo_unico",
        "token_nfc",
        "status",
    ];

    protected static array $uppers = [
        "codigo_unico",
        "observacoes",
        "motivo_bloqueio",
    ];

    public static function applySoftDeleteScope($query)
    {
        return $query->where("ct.trash", "=", 0);
    }
}
