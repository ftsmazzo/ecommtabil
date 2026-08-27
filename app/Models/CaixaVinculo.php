<?php

namespace App\Models;

use App\Core\Model;

class CaixaVinculo extends Model
{
    public static string $table = "caixa_vinculo";
    public static ?string $alias = "cv";

    public static function applySoftDeleteScope($query)
    {
        return $query->where("cv.trash", "=", 0);
    }
}
