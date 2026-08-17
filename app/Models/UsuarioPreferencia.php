<?php

namespace App\Models;

use App\Core\Model;

class UsuarioPreferencia extends Model
{

    public static string $table = "usuario_preferencia";

    public static function porUsuario(int $usuarioId)
    {
        return static::where('id_user', '=', $usuarioId)->first();
    }

}
