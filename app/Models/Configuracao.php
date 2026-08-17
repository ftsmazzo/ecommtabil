<?php

namespace App\Models;

use App\Core\Model;

class Configuracao extends Model
{
    public static string $table = "configuracao";
    public static ?string $alias = "c";

    public static function getValue(string $chave, mixed $default = null): mixed
    {
        $row = static::where("c.chave", "=", $chave)->first();
        return $row ? $row->valor : $default;
    }

    public static function setValue(string $chave, mixed $valor, ?int $updatedBy = null): void
    {
        $row = static::where("c.chave", "=", $chave)->first();
        if (!$row) {
            return;
        }

        static::updateBy((int) $row->id, [
            "valor"      => ($valor !== null && $valor !== "") ? (string) $valor : null,
            "updated_by" => $updatedBy,
            "updated_at" => date("Y-m-d H:i:s"),
        ]);
    }

    public static function allIndexed(): array
    {
        $rows = static::get();
        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row->chave] = $row->valor;
        }
        return $result;
    }
}
