<?php

namespace App\Models;

use App\Core\Model;

class Movimentacao extends Model
{
    public static string $table = "movimentacao";
    public static ?string $alias = "mv";

    protected static array $required = [
        "id_cartao",
        "tipo",
        "sentido",
        "valor",
        "saldo_anterior",
        "saldo_posterior",
        "origem",
    ];
}
