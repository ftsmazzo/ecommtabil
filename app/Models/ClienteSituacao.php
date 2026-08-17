<?php

namespace App\Models;

use App\Core\Model;

class ClienteSituacao extends Model
{
    public static string $table = "cliente_situacao";
    public static ?string $alias = "cs";
    public static array $uppers = ["descricao", "cor"];
}
