<?php

namespace App\Models;

use App\Core\Model;

class EmpresaResponsavel extends Model
{
    public static string $table = "empresa_responsavel";
    public static ?string $alias = "er";
    public static array $uppers = ["nome", "funcao", "endereco"];
}
