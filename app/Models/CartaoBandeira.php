<?php

namespace App\Models;

use App\Core\Model;

class CartaoBandeira extends Model
{
    public static string $table = "cartao_bandeira";
    public static ?string $alias = "cb";
    public static array $uppers = ["nome", "bandeira"];
    public static array $files = ["logo"];
    public static ?string $mediaPath = "storage/media/cartao_bandeira/";
}
