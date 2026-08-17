<?php
namespace App\Enums;

enum StatusUsuario: int
{
    case INATIVO = 0;
    case ATIVO   = 1;

    public function label(): string
    {
        return match ($this) {
            self::ATIVO   => 'Ativo',
            self::INATIVO => 'Inativo',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::ATIVO   => '<span class="badge filled-outlined bg-success">Ativo</span>',
            self::INATIVO => '<span class="badge filled-outlined bg-secondary">Inativo</span>',
        };
    }

    public function isAtivo(): bool
    {
        return $this === self::ATIVO;
    }
}
