<?php

namespace App\Models;

use App\Core\Model;

class IndiceFinanceiro extends Model
{
    public static string $table = "indice_financeiro";
    public static ?string $alias = "idx_fin";
    public static array $uppers = ["nome", "sigla"];
}
