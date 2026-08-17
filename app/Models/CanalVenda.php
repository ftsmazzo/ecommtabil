<?php

namespace App\Models;

use App\Core\Model;
use App\Enums\CanalVendaTipo;

class CanalVenda extends Model
{
    public static string $table = "canal_venda";
    public static ?string $alias = "cv";
    public static array $uppers = ["nome"];
    public static array $files = ["logo"];
    public static ?string $mediaPath = "storage/media/canal_venda/";

    public function tipoLabel(): string
    {
        if (!$this->tipo) {
            return "—";
        }
        $enum = CanalVendaTipo::tryFrom($this->tipo);
        return $enum ? $enum->label() : $this->tipo;
    }
}
