<?php

namespace App\Models;

use App\Core\Model;

class Projeto extends Model
{
    public static string $table = "projeto";
    public static ?string $alias = "p";
    protected static array $required = [
        "id_empresa",
    ];
    public static array $uppers = [];

    public static function applySoftDeleteScope($query)
    {
        return $query->where("p.trash", "=", 0);
    }
}
