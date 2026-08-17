<?php
namespace App\Models;

use App\Core\Model;
use App\Enums\StatusUsuario;

class Usuario extends Model
{

    public static string $table = "usuario";
    public static array $files = ["foto"];
    public static ?string $mediaPath = "storage/media/usuarios/";
    public static array $uppers = ["nome", "cargo"];

    public function status(): StatusUsuario
    {
        return StatusUsuario::from((int) $this->status);
    }

    public function statusBadge(): string
    {
        return $this->status()->badge();
    }

    public function isAtivo(): bool
    {
        return $this->status() === StatusUsuario::ATIVO;
    }

}