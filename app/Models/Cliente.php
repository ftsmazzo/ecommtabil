<?php

namespace App\Models;

use App\Core\Model;

class Cliente extends Model
{
    public static string $table = "cliente";
    public static ?string $alias = "c";
    protected static array $required = [
        "razao",
    ];
    public static array $uppers = [
        "rg_ie",
        "razao",
        "nome",
        "pessoa",
        "documento",
        "contato",
        "telefone",
        "whatsapp",
        "endereco",
        "numero",
        "complemento",
        "bairro",
        "cidade",
        "estado",
        "pais"
    ];

    public static function applySoftDeleteScope($query)
    {
        return $query->where("c.trash", "=", 0);
    }
}
