<?php

namespace App\Models;

use App\Core\Model;

class Banco extends Model
{
    public static string $table = "banco";
    public static ?string $alias = "b";
    public static array $uppers = ["nome"];
    public static array $files = ["logo"];
    public static ?string $mediaPath = "storage/media/banco/";
}
