<?php

namespace App\Models;

use App\Core\Model;

class ClienteRamo extends Model
{
    public static string $table = "cliente_ramo";
    public static ?string $alias = "cr";
    public static array $uppers = ["descricao"];
}
