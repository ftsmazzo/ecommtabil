<?php

namespace App\Models;

use App\Core\Model;

class Empresa extends Model
{
    public static string $table = "empresa";
    public static ?string $alias = "e";
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
        "pais",
    ];

    public static function applySoftDeleteScope($query)
    {
        return $query->where("e.trash", "=", 0);
    }
}
